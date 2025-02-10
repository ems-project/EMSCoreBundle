<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Common\Spreadsheet\SpreadsheetValidation;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Data\Condition\ConditionInterface;
use Symfony\Component\Translation\TranslatableMessage;

class TableColumn
{
    private string $attribute;
    private ?string $routeName = null;
    private ?\Closure $routeParametersCallback = null;
    private ?string $routeTarget = null;
    private ?string $iconClass = null;
    private ?\Closure $itemIconCallback = null;
    private string $cellType = 'td';
    private string $cellClass = '';
    private ?\Closure $dataClassCallback = null;
    private bool $cellRender = true;
    /** @var array <string, \Closure> */
    private array $htmlAttributes = [];
    private ?\Closure $pathCallback = null;
    private string $pathTarget = '';
    /** @var ConditionInterface[] */
    private array $conditions = [];
    private string $orderField;
    /** @var array<string, mixed> */
    private array $transLabelOptions = [];
    private ?SpreadsheetValidation $validation = null;

    public function __construct(private readonly string|TranslatableMessage $titleKey, string $attribute)
    {
        $this->orderField = $this->attribute = $attribute;
    }

    public function addCondition(ConditionInterface $condition): void
    {
        $this->conditions[] = $condition;
    }

    /**
     * @return ConditionInterface[]
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getTitleKey(): TranslatableMessage
    {
        if ($this->titleKey instanceof TranslatableMessage) {
            return $this->titleKey;
        }

        return new TranslatableMessage($this->titleKey, $this->transLabelOptions, EMSCoreBundle::TRANS_DOMAIN);
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function setAttribute(string $attribute): void
    {
        $this->attribute = $attribute;
    }

    public function setRoute(string $name, ?\Closure $callback = null, ?string $target = null): void
    {
        $this->routeName = $name;
        $this->routeParametersCallback = $callback;
        $this->routeTarget = $target;
    }

    public function cellRender(): bool
    {
        return $this->cellRender;
    }

    public function setCellRender(bool $value): void
    {
        $this->cellRender = $value;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    /**
     * @return array<mixed>
     */
    public function getFrontendOptions(): array
    {
        return [
            'cellType' => $this->cellType,
            'className' => $this->cellClass,
        ];
    }

    public function setCellType(string $cellType): TableColumn
    {
        if (!\in_array($cellType, ['td', 'tr'])) {
            throw new \RuntimeException(\sprintf('Unexpected cellType option %s, only td and tr are accepted', $cellType));
        }
        $this->cellType = $cellType;

        return $this;
    }

    public function setCellClass(string $cellClass): TableColumn
    {
        $this->cellClass = $cellClass;

        return $this;
    }

    public function setDataClassCallback(\Closure $callback): void
    {
        $this->dataClassCallback = $callback;
    }

    public function getDataClass(mixed $data): ?string
    {
        return $this->dataClassCallback?->call($this, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRouteProperties(mixed $data): ?array
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

    public function getItemIconClass(mixed $data): ?string
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

    public function addHtmlAttribute(string $key, \Closure $callback): TableColumn
    {
        $this->htmlAttributes[$key] = $callback;

        return $this;
    }

    /**
     * @return array<string, \Closure>
     */
    public function getHtmlAttributes(mixed $data): array
    {
        $out = [];
        foreach ($this->htmlAttributes as $htmlAttribute => $callValue) {
            $out[$htmlAttribute] = $callValue->call($this, $data);
        }

        return $out;
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

    public function setPathCallback(\Closure $pathCallback, string $target = ''): void
    {
        $this->pathCallback = $pathCallback;
        $this->pathTarget = $target;
    }

    public function hasPath(mixed $context, string $baseUrl): bool
    {
        return null !== $this->pathCallback && \is_string($this->pathCallback->call($this, $context, $baseUrl));
    }

    public function getPath(mixed $context, string $baseUrl): string
    {
        if (null === $this->pathCallback) {
            throw new \RuntimeException('Unexpected null pathCallback. use the hasPathCallback first');
        }
        $path = $this->pathCallback->call($this, $context, $baseUrl);
        if (!\is_string($path)) {
            throw new \RuntimeException('Unexpected null pathCallback. use the hasPathCallback first');
        }

        return $path;
    }

    public function getPathTarget(): string
    {
        return $this->pathTarget;
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function valid($objectOrArray): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->valid($objectOrArray)) {
                return false;
            }
        }

        return true;
    }

    public function setOrderField(string $orderField): TableColumn
    {
        $this->orderField = $orderField;

        return $this;
    }

    public function getOrderField(): string
    {
        return $this->orderField;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setLabelTransOption(array $options): void
    {
        $this->transLabelOptions = $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransLabelOptions(): array
    {
        return $this->transLabelOptions;
    }

    public function getValidation(): ?SpreadsheetValidation
    {
        return $this->validation;
    }

    public function setValidation(?SpreadsheetValidation $validation): void
    {
        $this->validation = $validation;
    }
}
