<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

class DatetimeTableColumn extends TableColumn
{
    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data_datetime';
    }

    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_datetime';
    }
}
