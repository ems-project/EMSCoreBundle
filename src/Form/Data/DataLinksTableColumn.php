<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class DataLinksTableColumn extends TableColumn
{
    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data_data_links';
    }

    public function getOrderable(): bool
    {
        return false;
    }
}
