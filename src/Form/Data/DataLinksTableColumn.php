<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class DataLinksTableColumn extends TableColumn
{
    #[\Override]
    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_data_links';
    }

    #[\Override]
    public function getOrderable(): bool
    {
        return false;
    }
}
