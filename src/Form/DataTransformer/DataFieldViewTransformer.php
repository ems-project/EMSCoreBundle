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
    public function __construct(private readonly FieldType $fieldType, private readonly FormRegistryInterface $formRegistry)
    {
    }

    /**
     * Transforms from Norm to View (DataField to array).
     *
     * @param DataField $data
     *
     * @return array<mixed>|string|int|float|bool|null
     */
    #[\Override]
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
    #[\Override]
    public function reverseTransform($data): DataField
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($this->fieldType->getType())->getInnerType();

        return $dataFieldType->reverseViewTransform($data, $this->fieldType);
    }
}
