<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

class Menu
{
    /** @var MenuEntry[] */
    private array $children = [];
    private string $title;
    /**
     * @var array<string, mixed>
     */
    private array $transParameters;

    /**
     * @param array<string, mixed> $transParameters
     */
    public function __construct(string $title, array $transParameters = [])
    {
        $this->title = $title;
        $this->transParameters = $transParameters;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function addChild(string $getLabel, string $getIcon, string $route, array $routeParameters = [], ?string $color = null): MenuEntry
    {
        return $this->children[] = new MenuEntry($getLabel, $getIcon, $route, $routeParameters, $color);
    }

    public function addMenuEntry(MenuEntry $menuEntry): void
    {
        $this->children[] = $menuEntry;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransParameters(): array
    {
        return $this->transParameters;
    }
}
