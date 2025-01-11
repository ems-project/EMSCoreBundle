<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class TemplateTableColumn extends TableColumn
{
    private const string LABEL = 'label';
    private const string TEMPLATE = 'template';
    private const string ORDER_FIELD = 'orderField';
    private const string CELL_TYPE = 'cellType';
    private const string CELL_CLASS = 'cellClass';
    private const string CELL_RENDER = 'cellRender';
    private readonly bool $orderable;
    private readonly string $template;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $options = self::resolveOptions($options);
        $this->orderable = null !== $options[self::ORDER_FIELD];
        $this->template = $options[self::TEMPLATE];
        parent::__construct($options[self::LABEL], $options[self::ORDER_FIELD] ?? 'not orderable');
        $this->setCellClass($options[self::CELL_CLASS]);
        $this->setCellType($options[self::CELL_TYPE]);
        $this->setCellRender($options[self::CELL_RENDER]);
    }

    #[\Override]
    public function getOrderable(): bool
    {
        return $this->orderable;
    }

    #[\Override]
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
            ->setAllowedTypes(self::LABEL, ['string', TranslatableMessage::class])
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
