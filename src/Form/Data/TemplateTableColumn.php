<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use Symfony\Component\OptionsResolver\OptionsResolver;

final class TemplateTableColumn extends TableColumn
{
    private const LABEL = 'label';
    private const TEMPLATE = 'template';
    private const ORDER_FIELD = 'orderField';
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
     * @return array{label: string, template: string, orderField: string|null}
     */
    private static function resolveOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                self::LABEL => 'Label',
                self::TEMPLATE => '',
                self::ORDER_FIELD => null,
            ])
            ->setAllowedTypes(self::LABEL, ['string'])
            ->setAllowedTypes(self::TEMPLATE, ['string'])
            ->setAllowedTypes(self::ORDER_FIELD, ['string', 'null'])
        ;
        /** @var array{label: string, template: string, orderField: string|null} $resolvedParameter */
        $resolvedParameter = $resolver->resolve($options);

        return $resolvedParameter;
    }
}
