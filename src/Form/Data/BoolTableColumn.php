<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class BoolTableColumn extends TableColumn
{
    #[\Override]
    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data_bool';
    }

    #[\Override]
    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_bool';
    }
}
