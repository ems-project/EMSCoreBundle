<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Symfony\Component\Translation\TranslatableMessage;

class Menu
{
    /** @var MenuEntry[] */
    private array $children = [];

    public function __construct(private readonly TranslatableMessage $title)
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
        return [] !== $this->children;
    }

    public function getTitle(): TranslatableMessage
    {
        return $this->title;
    }
}
