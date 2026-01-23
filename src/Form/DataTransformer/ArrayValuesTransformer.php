<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<mixed, mixed>
 */
class ArrayValuesTransformer implements DataTransformerInterface
{
    /**
     * @return mixed[]|null
     */
    #[\Override]
    public function transform(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        if (!\is_array($value)) {
            throw new \RuntimeException('Unexpected non-array object');
        }

        return \array_values($value);
    }

    /**
     * @return mixed[]|null
     */
    #[\Override]
    public function reverseTransform(mixed $value): ?array
    {
        return $this->transform($value);
    }
}
