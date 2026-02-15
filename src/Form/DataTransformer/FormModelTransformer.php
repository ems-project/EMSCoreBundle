<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataTransformer;

use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormRegistryInterface;

/**
 * @implements DataTransformerInterface<mixed, mixed>
 */
class FormModelTransformer implements DataTransformerInterface
{
    private readonly DataFieldModelTransformer $nestedTransformer;

    public function __construct(private readonly FieldType $fieldType, FormRegistryInterface $formRegistry)
    {
        $this->nestedTransformer = new DataFieldModelTransformer($fieldType, $formRegistry);
    }

    #[\Override]
    public function transform(mixed $value): DataField
    {
        $data = RawDataTransformer::transform($this->fieldType, $value ?? []);

        return $this->nestedTransformer->transform($data);
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function reverseTransform(mixed $value): array
    {
        $data = $this->nestedTransformer->reverseTransform($value);
        if (!\is_array($data)) {
            throw new \RuntimeException("Unexpected non-array form's data");
        }

        return RawDataTransformer::reverseTransform($this->fieldType, $data);
    }
}
