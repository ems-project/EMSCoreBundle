<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

class EntityTableColumn extends TableColumn
{
    #[\Override]
    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_entity';
    }
}
