<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CommonBundle\Json\JsonMenuNestedException;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedNode;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\JsonMenuNestedTemplate;
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
     * @param array{
     *     active_item_id?: string,
     *     load_parent_ids?: string[],
     *     load_children_id?: string
     * } $data
     *
     * @return array{ load_parent_ids: string[], tree: string }
     */
    public function render(JsonMenuNestedConfig $config, array $data): array
    {
        $menu = $config->jsonMenuNested;
        $activeItem = isset($data['active_item_id']) ? $menu->getItemById($data['active_item_id']) : null;
        $loadChildren = isset($data['load_children_id']) ? $menu->getItemById($data['load_children_id']) : null;

        $loadParentIds = \array_unique($data['load_parent_ids'] ?? []);
        $loadParents = \array_filter(\array_map(static fn (string $id) => $menu->getItemById($id), $loadParentIds));

        if ($loadChildren) {
            $loadParents[] = $loadChildren;
            foreach ($loadChildren as $loadParentChild) {
                if ($loadParentChild->hasChildren()) {
                    $loadParents[] = $loadParentChild;
                }
            }
        }

        $loadParents = \array_values(\array_unique($loadParents));

        return [
            'load_parent_ids' => \array_map(static fn (JsonMenuNested $item) => $item->getId(), $loadParents),
            'tree' => $this->getTemplate($config)->block('_jmn_items', [
                'menu' => $menu,
                'activeItem' => $activeItem ?? $menu,
                'loadParents' => $loadParents,
            ]),
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

    private function getTemplate(JsonMenuNestedConfig $config): JsonMenuNestedTemplate
    {
        return $this->jsonMenuNestedTemplateFactory->create($config);
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
