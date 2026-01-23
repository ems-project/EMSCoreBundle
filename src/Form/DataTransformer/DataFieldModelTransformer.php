<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataTransformer;

use EMS\CoreBundle\Core\ContentType\DataFieldFormOptions;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormRegistryInterface;

/**
 * @implements DataTransformerInterface<mixed, mixed>
 */
class DataFieldModelTransformer implements DataTransformerInterface
{
    private ?DataFieldFormOptions $formOptions = null;

    public function __construct(private readonly FieldType $fieldType, private readonly FormRegistryInterface $formRegistry)
    {
    }

    public static function withFormOptions(FieldType $fieldType, FormRegistryInterface $formRegistry, DataFieldFormOptions $viewOptions): self
    {
        $transformer = new self($fieldType, $formRegistry);
        $transformer->formOptions = $viewOptions;

        return $transformer;
    }

    /**
     * Transforms from Model to Norm (array to Datafield).
     */
    #[\Override]
    public function transform(mixed $value): DataField
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($this->fieldType->getType())->getInnerType();
        $dataFieldType->setFormOptions($this->formOptions);

        return $dataFieldType->modelTransform($value, $this->fieldType);
    }

    /**
     * Transforms from Norm to Model (DataField to array).
     */
    #[\Override]
    public function reverseTransform(mixed $value): mixed
    {
        /** @var DataFieldType $dataFieldType */
        $dataFieldType = $this->formRegistry->getType($this->fieldType->getType())->getInnerType();
        $dataFieldType->setFormOptions($this->formOptions);

        return $dataFieldType->reverseModelTransform($value);
    }
}
