<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

class MenuEntry
{
    private string $label;
    private string $icon;
    private string $route;
    /**
     * @var array<string, mixed>
     */
    private array $routeParameters;
    private ?string $color;
    private ?string $badge = null;
    /** @var MenuEntry[] */
    private array $children = [];
    private bool $translation = false;
    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];
    private ?string $badgeColor;

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function __construct(string $label, string $icon, string $route, array $routeParameters = [], ?string $color = null)
    {
        $this->label = $label;
        $this->icon = $icon;
        $this->route = $route;
        $this->routeParameters = $routeParameters;
        $this->color = $color;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function addChild(string $getLabel, string $getIcon, string $route, array $routeParameters = [], ?string $color = null): MenuEntry
    {
        return $this->children[] = new MenuEntry($getLabel, $getIcon, $route, $routeParameters, $color);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function setRouteParameters(array $routeParameters): void
    {
        $this->routeParameters = $routeParameters;
    }

    public function getColor(): ?string
    {
        return $this->color;
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
        return \count($this->children) > 0;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function setTranslation(array $parameters): void
    {
        $this->translation = true;
        $this->parameters = $parameters;
    }

    public function isTranslation(): bool
    {
        return $this->translation;
    }

    /**
     * @return mixed[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
