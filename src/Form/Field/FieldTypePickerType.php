<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Form\DataField\DataFieldType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldTypePickerType extends SelectPickerType
{
    /** @var array<string, DataFieldType> */
    private array $dataFieldTypes = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function addDataFieldType(DataFieldType $dataField): void
    {
        $this->dataFieldTypes[\get_class($dataField)] = $dataField;
    }

    public function getDataFieldType(string $dataFieldTypeId): DataFieldType
    {
        return $this->dataFieldTypes[$dataFieldTypeId];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => \array_keys($this->dataFieldTypes),
            'attr' => [
                    'data-live-search' => true,
            ],
            'choice_attr' => function ($category, $key, $index) {
                $dataFieldType = $this->dataFieldTypes[$index];

                return [
                    'data-content' => '<div class="text-'.$category.'"><i class="'.$dataFieldType->getIcon().'"></i>&nbsp;&nbsp;'.$dataFieldType->getLabel().'</div>',
                ];
            },
            'choice_value' => fn ($value) => $value,
        ]);
    }
}
