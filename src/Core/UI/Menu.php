<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Symfony\Component\Translation\TranslatableMessage;

class Menu
{
    /** @var MenuEntry[] */
    private array $children = [];

    /**
     * @param array<string, mixed> $transParameters
     */
    public function __construct(private readonly string $title, private readonly array $transParameters = [])
    {
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function addChild(string|TranslatableMessage $label, string $icon, string $route, array $routeParameters = [], ?string $color = null): MenuEntry
    {
        return $this->children[] = new MenuEntry(
            label: $label,
            icon: $icon,
            route: $route,
            routeParameters: $routeParameters,
            color: $color
        );
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
