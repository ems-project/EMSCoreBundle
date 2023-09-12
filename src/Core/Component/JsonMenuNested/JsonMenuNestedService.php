<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CommonBundle\Json\JsonMenuNestedException;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedNode;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\Context\JsonMenuNestedRenderContext;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\JsonMenuNestedTemplateFactory;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class JsonMenuNestedService
{
    public function __construct(
        private readonly JsonMenuNestedTemplateFactory $jsonMenuNestedTemplateFactory,
        private readonly RevisionService $revisionService,
        private readonly UserService $userService
    ) {
    }

    /**
     * @return array{ load_parent_ids: string[], tree: string }
     */
    public function render(JsonMenuNestedConfig $config, ?string $activeItemId, ?string $loadChildrenId, string ...$loadParentIds): array
    {
        $menu = $config->jsonMenuNested;
        $renderContext = new JsonMenuNestedRenderContext($menu, $activeItemId, $loadChildrenId, ...$loadParentIds);

        $template = $this->jsonMenuNestedTemplateFactory->create($config, [
            'menu' => $menu,
            'render' => $renderContext,
        ]);

        return [
            'load_parent_ids' => $renderContext->getParentIds(),
            'tree' => $template->block('jmn_render'),
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
    public function itemAdd(JsonMenuNestedConfig $config, string $itemId, array $data): void
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setRequired(['add'])
            ->setDefault('position', null)
            ->setAllowedTypes('add', 'array')
            ->setAllowedTypes('position', 'int');

        /** @var array{add: array<mixed>, position?: int} $data */
        $data = $optionsResolver->resolve($data);

        $jsonMenuNested = $config->jsonMenuNested;
        $item = $jsonMenuNested->giveItemById($itemId);
        $addChild = new JsonMenuNested($data['add']);

        if (!$jsonMenuNested->hasChild($addChild)) {
            $item->addChild($addChild, $data['position'] ?? null);
            $this->saveStructure($config);
        }
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

    public function itemDelete(JsonMenuNestedConfig $config, string $itemId): void
    {
        $item = $config->jsonMenuNested->giveItemById($itemId);
        $item->giveParent()->removeChild($item);

        $this->saveStructure($config);
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
    }
}