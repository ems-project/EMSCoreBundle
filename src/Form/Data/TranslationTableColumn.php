<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use Symfony\Component\Translation\TranslatableMessage;

final class TranslationTableColumn extends TableColumn
{
    private ?string $keyPrefix = null;

    public function __construct(string|TranslatableMessage $titleKey, string $attribute, private readonly string $domain)
    {
        parent::__construct($titleKey, $attribute);
    }

    #[\Override]
    public function tableDataBlock(): string
    {
        return 'emsco_form_table_column_data_translation';
    }

    #[\Override]
    public function tableDataValueBlock(): string
    {
        return 'emsco_form_table_column_data_value_translation';
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getKeyPrefix(): ?string
    {
        return $this->keyPrefix;
    }

    public function setKeyPrefix(?string $keyPrefix): void
    {
        $this->keyPrefix = $keyPrefix ? $keyPrefix.'.' : '';
    }
}
