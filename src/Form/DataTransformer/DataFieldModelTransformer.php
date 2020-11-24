<?php

namespace EMS\CoreBundle\Form\DataTransformer;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormRegistryInterface;

class DataFieldModelTransformer implements DataTransformerInterface
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
     * Transforms from Model to Norm (array to Datafield).
     *
     * @param array $data
     *
     * @return DataField
     */
    public function transform($data)
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($this->fieldType->getType())->getInnerType();

        return $dataFieldType->modelTransform($data, $this->fieldType);
    }

    /**
     * Transforms from Norm to Model (DataField to array).
     *
     * @param DataField $data
     *
     * @return array|float|int|string|null
     */
    public function reverseTransform($data)
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($this->fieldType->getType())->getInnerType();

        return $dataFieldType->reverseModelTransform($data);
    }
}
