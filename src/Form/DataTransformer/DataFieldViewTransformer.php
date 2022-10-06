<?php

namespace EMS\CoreBundle\Form\DataTransformer;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormRegistryInterface;

/**
 * @implements DataTransformerInterface<mixed, mixed>
 */
class DataFieldViewTransformer implements DataTransformerInterface
{
    private FieldType $fieldType;
    private FormRegistryInterface $formRegistry;

    public function __construct(FieldType $fieldType, FormRegistryInterface $formRegistry)
    {
        $this->fieldType = $fieldType;
        $this->formRegistry = $formRegistry;
    }

    /**
     * Transforms from Norm to View (DataField to array).
     *
     * @param DataField $data
     *
     * @return array<mixed>|string|int|float|bool|null
     */
    public function transform($data)
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($this->fieldType->getType())->getInnerType();

        return $dataFieldType->viewTransform($data);
    }

    /**
     * Transforms from View to Norm (array to DataField).
     *
     * @param array<mixed>|string|int|float|bool|null $data from the Form
     */
    public function reverseTransform($data): DataField
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($this->fieldType->getType())->getInnerType();

        return $dataFieldType->reverseViewTransform($data, $this->fieldType);
    }
}
