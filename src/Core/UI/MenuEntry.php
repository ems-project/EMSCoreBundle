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

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function hasBadge(): bool
    {
        return null !== $this->badge;
    }

    public function setBadge(?string $badge): void
    {
        $this->badge = $badge;
    }
}
