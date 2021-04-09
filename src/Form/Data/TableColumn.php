<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

class TableColumn
{
    private string $titleKey;
    private string $attribute;
    private ?string $routeName = null;
    private ?\Closure $routeParametersCallback = null;
    private ?string $routeTarget = null;
    private ?string $iconClass = null;
    private ?\Closure $itemIconCallback = null;

    public function __construct(string $titleKey, string $attribute)
    {
        $this->titleKey = $titleKey;
        $this->attribute = $attribute;
    }

    public function getTitleKey(): string
    {
        return $this->titleKey;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function setRoute(string $name, ?\Closure $callback = null, ?string $target = null): void
    {
        $this->routeName = $name;
        $this->routeParametersCallback = $callback;
        $this->routeTarget = $target;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    /**
     * @param mixed $data
     *
     * @return array<string, mixed>
     */
    public function getRouteProperties($data): array
    {
        if (null === $this->routeParametersCallback) {
            return [];
        }

        return $this->routeParametersCallback->call($this, $data);
    }

    public function getRouteTarget(): ?string
    {
        return $this->routeTarget;
    }

    public function setItemIconCallback(\Closure $callback): void
    {
        $this->itemIconCallback = $callback;
    }

    /**
     * @param mixed $data
     */
    public function getItemIconClass($data): ?string
    {
        if (null === $this->itemIconCallback) {
            return null;
        }

        return $this->itemIconCallback->call($this, $data);
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

    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data';
    }

    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value';
    }

    public function getOrderable(): bool
    {
        return true;
    }
}
