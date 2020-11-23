<?php

namespace EMS\CoreBundle\Form\DataTransformer;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormRegistryInterface;

class DataFieldViewTransformer implements DataTransformerInterface
{
    /** @var FieldType */
    private $fieldType;
    /** @var FormRegistryInterface */
    private $formRegistry;

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
     * @return string|array
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
     * @param array|string|int|float|null $data from the Form
     *
     * @return DataField
     */
    public function reverseTransform($data)
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($this->fieldType->getType())->getInnerType();

        return $dataFieldType->reverseViewTransform($data, $this->fieldType);
    }
}
