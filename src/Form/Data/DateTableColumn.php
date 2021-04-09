<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class DateTableColumn extends DatetimeTableColumn
{
    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_date';
    }
}
