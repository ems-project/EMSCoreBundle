<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Form\DataField\DataFieldType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldTypePickerType extends Select2Type
{
    /** @var array<string, DataFieldType> */
    private array $dataFieldTypes = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function addDataFieldType(DataFieldType $dataField): void
    {
        $this->dataFieldTypes[$dataField::class] = $dataField;
    }

    public function getDataFieldType(string $dataFieldTypeId): DataFieldType
    {
        return $this->dataFieldTypes[$dataFieldTypeId];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => \array_keys($this->dataFieldTypes),
            'choice_label' => function ($value) {
                $dataFieldType = $this->dataFieldTypes[$value];
                $icon = $dataFieldType->getIcon();
                $label = $dataFieldType->getLabel();

                return "<i class=\"$icon\"></i>&nbsp;$label";
            },
            'choice_value' => fn ($value) => $value,
        ]);
    }
}
