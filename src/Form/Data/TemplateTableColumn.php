<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class TemplateTableColumn extends TableColumn
{
    public function __construct(array $column)
    {
        parent::__construct('test', 'demo');
    }

    public function getOrderable(): bool
    {
        return false;
    }

    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data_template';
    }

    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_template';
    }
}
