<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class Terms implements ConditionInterface
{
    /**
     * @param string[] $values
     */
    public function __construct(private readonly string $pathProperty, private readonly array $values)
    {
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    #[\Override]
    public function valid($objectOrArray): bool
    {
        $accessor = new PropertyAccessor();
        $field = $accessor->getValue($objectOrArray, $this->pathProperty);
        if (\is_array($field)) {
            return !empty(\array_intersect($field, $this->values));
        }

        return \in_array($field, $this->values);
    }
}
