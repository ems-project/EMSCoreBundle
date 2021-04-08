<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class RolesTableColumn extends TableColumn
{
    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data_roles';
    }

    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_roles';
    }

    public function getOrderable(): bool
    {
        return false;
    }
}
