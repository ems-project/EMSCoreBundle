<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use Symfony\Component\OptionsResolver\OptionsResolver;

class TemplateTableColumn extends TableColumn
{
    private const LABEL = 'label';
    private const TEMPLATE = 'template';
    private const ORDER_FIELD = 'orderField';
    private const CELL_TYPE = 'cellType';
    private const CELL_CLASS = 'cellClass';
    private const CELL_RENDER = 'cellRender';
    private bool $orderable;
    private string $template;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $options = $this->resolveOptions($options);
        $this->orderable = null !== $options[self::ORDER_FIELD];
        $this->template = $options[self::TEMPLATE];
        parent::__construct($options[self::LABEL], $options[self::ORDER_FIELD] ?? 'not orderable');
        $this->setCellClass($options[self::CELL_CLASS]);
        $this->setCellType($options[self::CELL_TYPE]);
        $this->setCellRender($options[self::CELL_RENDER]);
    }

    public function getOrderable(): bool
    {
        return $this->orderable;
    }

    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_template';
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{label: string, template: string, orderField: string|null, cellType: string, cellClass: string, cellRender: bool}
     */
    private static function resolveOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                self::LABEL => 'Label',
                self::TEMPLATE => '',
                self::ORDER_FIELD => null,
                self::CELL_TYPE => 'td',
                self::CELL_CLASS => '',
                self::CELL_RENDER => true,
            ])
            ->setAllowedTypes(self::LABEL, ['string'])
            ->setAllowedTypes(self::TEMPLATE, ['string'])
            ->setAllowedTypes(self::ORDER_FIELD, ['string', 'null'])
            ->setAllowedTypes(self::CELL_TYPE, ['string'])
            ->setAllowedTypes(self::CELL_CLASS, ['string'])
            ->setAllowedTypes(self::CELL_RENDER, ['bool'])
        ;
        /** @var array{label: string, template: string, orderField: string|null, cellType: string, cellClass: string, cellRender: bool} $resolvedParameter */
        $resolvedParameter = $resolver->resolve($options);

        return $resolvedParameter;
    }
}
