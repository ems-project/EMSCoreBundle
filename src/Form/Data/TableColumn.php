<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class TableColumn
{
    private string $titleKey;
    private string $attribute;
    /** @var array<mixed, string> */
    private array $valueToIconMapping;
    private ?string $routeProperty = null;
    private ?string $routePath = null;
    private ?\Closure $routeCallback;
    private ?string $routeTarget = '_blank';
    private ?string $iconProperty = null;
    private ?bool $dateTimeProperty = null;
    private ?bool $dataLinks = null;
    private string $class = 'nowrap';
    private ?string $iconClass = null;
    private bool $formatBytes = false;

    /**
     * @param array<mixed, string> $valueToIconMapping
     */
    public function __construct(string $titleKey, string $attribute, array $valueToIconMapping = [])
    {
        $this->titleKey = $titleKey;
        $this->attribute = $attribute;
        $this->valueToIconMapping = $valueToIconMapping;
    }

    public function getTitleKey(): string
    {
        return $this->titleKey;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * @return array<mixed, string>
     */
    public function getValueToIconMapping(): array
    {
        return $this->valueToIconMapping;
    }

    public function setRoutePath(string $routePath, ?\Closure $callback = null): void
    {
        $this->routePath = $routePath;
        $this->routeCallback = $callback;
    }

    public function setRouteProperty(string $routeProperty): void
    {
        $this->routeProperty = $routeProperty;
    }

    public function getRoutePath(): ?string
    {
        return $this->routePath;
    }

    /**
     * @param mixed $data
     *
     * @return array<string, mixed>
     */
    public function getRouteProperties($data): array
    {
        if (null === $this->routeCallback) {
            return [];
        }

        return $this->routeCallback->call($this, $data);
    }

    public function getRouteProperty(): ?string
    {
        return $this->routeProperty;
    }

    public function setRouteTarget(?string $target): ?string
    {
        return $this->routeTarget = $target;
    }

    public function getRouteTarget(): ?string
    {
        return $this->routeTarget;
    }

    public function getIconProperty(): ?string
    {
        return $this->iconProperty;
    }

    public function setIconProperty(?string $iconProperty): void
    {
        $this->iconProperty = $iconProperty;
    }

    public function getDateTimeProperty(): ?bool
    {
        return $this->dateTimeProperty;
    }

    public function setDateTimeProperty(?bool $dateTimeProperty): void
    {
        $this->dateTimeProperty = $dateTimeProperty;
    }

    public function getDataLinks(): ?bool
    {
        return $this->dataLinks;
    }

    public function setDataLinks(?bool $dataLinks): void
    {
        $this->dataLinks = $dataLinks;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function setIconClass(string $iconClass): void
    {
        if (\strlen($iconClass) > 0) {
            $this->iconClass = $iconClass;
        } else {
            $this->iconClass = null;
        }
    }

    public function getIconClass(): ?string
    {
        return $this->iconClass;
    }

    public function getFormatBytes(): bool
    {
        return $this->formatBytes;
    }

    public function setFormatBytes(bool $formatBytes): void
    {
        $this->formatBytes = $formatBytes;
    }
}
