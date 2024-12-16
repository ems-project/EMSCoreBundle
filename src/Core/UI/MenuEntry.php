<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

class MenuEntry
{
    private ?string $badge = null;
    /** @var MenuEntry[] */
    private array $children = [];
    private bool $translation = false;
    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];
    private ?string $badgeColor = null;

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function __construct(
        private readonly string|TranslatableMessage $label,
        private readonly string $icon,
        private string $route,
        private array $routeParameters = [],
        private readonly ?string $color = null,
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

    public function getLabel(): string
    {
        if ($this->label instanceof TranslatableMessage) {
            return $this->label->getMessage();
        }

        return $this->label;
    }

    public function getLabelTranslation(): ?TranslatableMessage
    {
        if ($this->label instanceof TranslatableMessage) {
            return $this->label;
        }

        if (!$this->isTranslation()) {
            return null;
        }

        return t($this->label, $this->parameters, 'emsco-twigs');
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
