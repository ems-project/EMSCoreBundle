<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class TranslationTableColumn extends TableColumn
{
    public function __construct(string $titleKey, string $attribute, private readonly string $domain)
    {
        parent::__construct($titleKey, $attribute);
    }

    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data_translation';
    }

    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_translation';
    }

    public function getDomain(): string
    {
        return $this->domain;
    }
}
