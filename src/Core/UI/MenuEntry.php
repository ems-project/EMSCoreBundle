<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Symfony\Component\Translation\TranslatableMessage;

class MenuEntry
{
    private ?string $badge = null;
    /** @var MenuEntry[] */
    private array $children = [];
    private ?string $badgeColor = null;

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function __construct(
        public readonly string|TranslatableMessage $label,
        public readonly string $icon,
        public string $route,
        public array $routeParameters = [],
        public readonly ?string $color = null,
    ) {
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

    public function getBadgeColor(): ?string
    {
        return $this->badgeColor ?? $this->color;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function hasBadge(): bool
    {
        return null !== $this->badge;
    }

    public function setBadge(?string $badge, ?string $color = null): void
    {
        $this->badge = $badge;
        $this->badgeColor = $color;
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
}
