<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class RevisionDisplayTableColumn extends TableColumn
{
    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data_revision_display';
    }

    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_revision_display';
    }
}
