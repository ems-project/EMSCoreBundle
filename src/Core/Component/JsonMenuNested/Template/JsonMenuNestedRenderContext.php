<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Template;

use EMS\CommonBundle\Json\JsonMenuNested;

class JsonMenuNestedRenderContext
{
    public ?JsonMenuNested $activeItem;

    /** @var array<string, JsonMenuNested> */
    public array $loadParents = [];
    /** @var array<string, JsonMenuNested> */
    public array $loadItems = [];

    public function __construct(
        private readonly JsonMenuNested $menu,
        ?string $activeItemId = null,
        public ?JsonMenuNested $copyItem = null,
        ?string $loadChildrenId = null
    ) {
        $this->addActiveItem($menu);

        $this->activeItem = $activeItemId ? $menu->getItemById($activeItemId) : null;
        if ($this->activeItem) {
            $this->loadPath($this->activeItem);
        }

        $loadChildren = $loadChildrenId ? $this->menu->getItemById($loadChildrenId) : null;
        if ($loadChildren) {
            $this->loadAllChildren($loadChildren);
        }
    }

    public function loadPath(JsonMenuNested $item): void
    {
        foreach ($item->getPath() as $itemParent) {
            $this->addParent($itemParent);
        }
    }

    public function loadAllChildren(JsonMenuNested $item): void
    {
        foreach ($item as $child) {
            if ($child->hasChildren()) {
                $this->addParent($child);
            }
        }
    }

    public function loadParents(string ...$loadParentIds): void
    {
        foreach ($loadParentIds as $loadParentId) {
            $this->addParent($this->menu->getItemById($loadParentId));
        }
    }

    /**
     * @return string[]
     */
    public function getItemIds(): array
    {
        return \array_values(\array_map(static fn (JsonMenuNested $item) => $item->getId(), $this->loadItems));
    }

    /**
     * @return string[]
     */
    public function getParentIds(): array
    {
        return \array_values(\array_map(static fn (JsonMenuNested $item) => $item->getId(), $this->loadParents));
    }

    private function addParent(?JsonMenuNested $parent = null): void
    {
        if (null === $parent) {
            return;
        }

        if (\array_key_exists($parent->getId(), $this->loadParents)) {
            return;
        }

        $this->loadParents[$parent->getId()] = $parent;
        $this->addActiveItem($parent);
    }

    private function addActiveItem(JsonMenuNested $item): void
    {
        if (!$item->isRoot()) {
            $this->loadItems[$item->getId()] = $item;
        }

        foreach ($item->getChildren() as $child) {
            $this->loadItems[$child->getId()] = $child;
        }
    }
}
