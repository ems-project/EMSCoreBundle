<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CommonBundle\Json\JsonMenuNestedException;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedNode;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\JsonMenuNestedRenderContext;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\JsonMenuNestedTemplateFactory;
use EMS\CoreBundle\Core\UI\Modal\Modal;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class JsonMenuNestedService
{
    private const SESSION_COPY_KEY = 'jmn_copy';

    public function __construct(
        private readonly JsonMenuNestedTemplateFactory $jsonMenuNestedTemplateFactory,
        private readonly RevisionService $revisionService,
        private readonly UserService $userService,
        private readonly ElasticaService $elasticaService,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @return array{ loadParentIds: string[], tree: string, top: string, footer: string }
     */
    public function render(JsonMenuNestedConfig $config, ?string $activeItemId, ?string $loadChildrenId, string ...$loadParentIds): array
    {
        $menu = $config->jsonMenuNested;
        $renderContext = new JsonMenuNestedRenderContext(
            menu: $menu,
            activeItemId: $activeItemId,
            copyItem: $this->getCopiedItem(),
            loadChildrenId: $loadChildrenId
        );
        $renderContext->loadParents(...$loadParentIds);

        $template = $this->jsonMenuNestedTemplateFactory->create($config, ['render' => $renderContext]);

        return [
            'loadParentIds' => $renderContext->getParentIds(),
            'tree' => $template->block('jmn_render'),
            'top' => $template->block('jmn_layout_top'),
            'footer' => $template->block('jmn_layout_footer'),
        ];
    }

    /**
     * @param array<string, mixed> $object
     */
    public function itemCreate(JsonMenuNestedConfig $config, JsonMenuNested $parent, JsonMenuNestedNode $node, array $object): JsonMenuNested
    {
        $item = JsonMenuNested::create($node->type, $object);

        $parent->addChild($item);
        $this->saveStructure($config);

        return $item;
    }

    /**
     * @param array{add: array<mixed>, position?: int}|array<mixed> $data
     */
    public function itemAdd(JsonMenuNestedConfig $config, string $itemId, array $data): ?JsonMenuNested
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setRequired(['add'])
            ->setDefault('position', null)
            ->setAllowedTypes('add', 'array')
            ->setAllowedTypes('position', ['null', 'int']);

        /** @var array{add: array<mixed>, position?: int} $data */
        $data = $optionsResolver->resolve($data);

        $jsonMenuNested = $config->jsonMenuNested;
        $item = $jsonMenuNested->giveItemById($itemId);
        $addData = $data['add'];

        $addChild = isset($addData['id']) ? new JsonMenuNested($addData) : JsonMenuNested::create($addData['type'], $addData['object']);

        if ($jsonMenuNested->hasChild($addChild)) {
            return null;
        }

        $item->addChild($addChild, $data['position'] ?? null);
        $this->saveStructure($config);

        return $addChild;
    }

    /**
     * @param array<string, mixed> $object
     */
    public function itemUpdate(JsonMenuNestedConfig $config, JsonMenuNested $item, array $object): void
    {
        if (isset($object['label'])) {
            $item->setLabel($object['label']);
        }

        $item->setObject($object);
        $this->saveStructure($config);
    }

    public function itemDelete(JsonMenuNestedConfig $config, JsonMenuNested $item): void
    {
        $item->giveParent()->removeChild($item);
        $this->saveStructure($config);
    }

    public function itemCopy(JsonMenuNestedConfig $config, JsonMenuNested $item): void
    {
        $item->changeIds();

        foreach ($config->nodes->getClearPathsByType() as $type => $paths) {
            $items = \array_filter($item->toArray(), static fn (JsonMenuNested $item) => $item->getType() === $type);
            \array_walk($items, static fn (JsonMenuNested $item) => $item->clear($paths));
        }

        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_COPY_KEY, Json::encode($item->toArrayStructure(true)));
    }

    public function itemPaste(JsonMenuNestedConfig $config, JsonMenuNested $item): JsonMenuNested
    {
        if (null === $copiedItem = $this->getCopiedItem()) {
            throw new \RuntimeException('No item copied');
        }

        if ($item->getType() === $config->nodes->root->type && $copiedItem->getType() === $item->getType()) {
            foreach ($copiedItem->getChildren() as $copiedChild) {
                $item->addChild($copiedChild);
            }
        } else {
            $node = $config->nodes->getByType($item->getType());
            $children = $config->nodes->getChildren($node);

            if (!\array_key_exists($copiedItem->getType(), $children)) {
                throw new \RuntimeException('Copy item not allowed');
            }

            $item->addChild($copiedItem);
        }

        $this->saveStructure($config);

        return $copiedItem;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function itemModal(JsonMenuNestedConfig $config, JsonMenuNested $item, string $modalName, array $context = []): Modal
    {
        $context['item'] = $item;
        $template = $this->jsonMenuNestedTemplateFactory->create($config, $context);

        $blocks = [];
        foreach (['title', 'body', 'footer'] as $block) {
            if ($template->hasBlock($modalName.'_'.$block)) {
                $blocks[$block] = $template->block($modalName.'_'.$block);
            }
        }

        $modal = new Modal(...$blocks);
        $modal->data['modalName'] = $modalName;
        $modal->data['item'] = $item->getData();

        return $modal;
    }

    /**
     * @param array{fromParentId: string, toParentId: string, position: int}|array<mixed> $data
     *
     * @throws JsonMenuNestedException
     */
    public function itemMove(JsonMenuNestedConfig $config, string $itemId, array $data): void
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setRequired(['fromParentId', 'toParentId', 'position'])
            ->setAllowedTypes('fromParentId', 'string')
            ->setAllowedTypes('toParentId', 'string')
            ->setAllowedTypes('position', 'int');

        /** @var array{fromParentId: string, toParentId: string, position: int} $data */
        $data = $optionsResolver->resolve($data);

        $jsonMenuNested = $config->jsonMenuNested;
        $item = $jsonMenuNested->giveItemById($itemId);
        $fromParent = $jsonMenuNested->giveItemById($data['fromParentId']);
        $toParent = $jsonMenuNested->giveItemById($data['toParentId']);

        $jsonMenuNested->moveChild($item, $fromParent, $toParent, $data['position']);
        $this->saveStructure($config);
    }

    private function saveStructure(JsonMenuNestedConfig $config): void
    {
        $path = $config->nodes->path;
        $structure = Json::encode($config->jsonMenuNested->toArrayStructure());
        $username = $this->userService->getCurrentUser()->getUsername();

        $rawData = [];
        (new PropertyAccessor())->setValue($rawData, $path, $structure);

        $this->revisionService->updateRawData($config->revision, $rawData, $username);
        $this->elasticaService->refresh($config->revision->giveContentType()->giveEnvironment()->getAlias());
    }

    private function getCopiedItem(): ?JsonMenuNested
    {
        $session = $this->requestStack->getSession();
        $copiedJson = $session->get(self::SESSION_COPY_KEY);

        return $copiedJson ? new JsonMenuNested(Json::decode($copiedJson)) : null;
    }
}
