<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

class Menu
{
    /** @var MenuEntry[] */
    private array $children = [];

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function addChild(string $getLabel, string $getIcon, string $route, array $routeParameters = [], ?string $color = null): MenuEntry
    {
        return $this->children[] = new MenuEntry($getLabel, $getIcon, $route, $routeParameters, $color);
    }

    /**
     * @return MenuEntry[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return \count($this->children) > 0;
    }
}
