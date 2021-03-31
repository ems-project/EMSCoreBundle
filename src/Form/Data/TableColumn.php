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
    private ?string $routeTarget = '_blank';
    private ?string $iconProperty = null;
    private ?bool $dateTimeProperty = null;
    private ?bool $dataLinks = false;
    private ?bool $arrayOfStrings = false;
    private bool $noWrap = true;
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

    public function setRoutePath(string $routePath): void
    {
        $this->routePath = $routePath;
    }

    public function setRouteProperty(string $routeProperty): void
    {
        $this->routeProperty = $routeProperty;
    }

    public function getRoutePath(): ?string
    {
        return $this->routePath;
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

    public function getArrayOfStrings(): ?bool
    {
        return $this->arrayOfStrings;
    }

    public function setArrayOfStrings(?bool $arrayOfStrings): void
    {
        $this->arrayOfStrings = $arrayOfStrings;
    }

    public function isNoWrap(): bool
    {
        return $this->noWrap;
    }

    public function setNoWrap(bool $noWrap): void
    {
        $this->noWrap = $noWrap;
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
