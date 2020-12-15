<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Table;

final class TableAction
{
    /**
     * @var bool
     */
    private $post;
    /**
     * @var string
     */
    private $route;
    /**
     * @var array<string, mixed>
     */
    private $routeParameters;
    /**
     * @var string
     */
    private $attributeName;
    /**
     * @var string
     */
    private $icon;
    /**
     * @var string
     */
    private $labelKey;
    /**
     * @var string|null
     */
    private $messageKey;

    /**
     * @param array<string, mixed> $routeParameters
     */
    private function __construct(bool $post, string $route, string $attributeName, string $labelKey, string $icon, ?string $messageKey, array $routeParameters)
    {
        $this->post = $post;
        $this->route = $route;
        $this->attributeName = $attributeName;
        $this->routeParameters = $routeParameters;
        $this->labelKey = $labelKey;
        $this->icon = $icon;
        $this->messageKey = $messageKey;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public static function postAction(string $route, string $attributeName, string $labelKey, string $icon, string $messageKey, array $routeParameters = []): TableAction
    {
        return new self(true, $route, $attributeName, $labelKey, $icon, $messageKey, $routeParameters);
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public static function getAction(string $route, string $attributeName, string $labelKey, string $icon, array $routeParameters = []): TableAction
    {
        return new self(false, $route, $attributeName, $labelKey, $icon, null, $routeParameters);
    }

    public function isPost(): bool
    {
        return $this->post;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getAttributeName(): string
    {
        return $this->attributeName;
    }

    public function getLabelKey(): string
    {
        return $this->labelKey;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getMessageKey(): ?string
    {
        return $this->messageKey;
    }
}
