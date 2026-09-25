<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use Symfony\Component\Translation\TranslatableMessage;

final class BoolTableColumn extends TableColumn
{
    public function __construct(
        string|TranslatableMessage $titleKey,
        string $attribute,
        private readonly string $iconType = 'square'
    ) {
        parent::__construct($titleKey, $attribute);
    }

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

    public function getIconType(): string
    {
        return $this->iconType;
    }
}
