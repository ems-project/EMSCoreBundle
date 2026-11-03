<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Form\DataField\DataFieldType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldTypePickerType extends ChoiceType
{
    /** @var array<string, DataFieldType> */
    private array $dataFieldTypes = [];

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
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'choices' => \array_keys($this->dataFieldTypes),
            'choice_label' => function ($value) {
                return $this->dataFieldTypes[$value]->getLabel();
            },
            'choice_attr' => fn ($key) => [
                'data-icon' => $this->dataFieldTypes[$key]->getIcon(),
            ],
            'choice_value' => fn ($value) => $value,
            'attr' => [
                'class' => 'select2',
            ],
        ]);
    }
}
