<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class Terms implements ConditionInterface
{
    private string $pathProperty;
    /** @var string[] */
    private array $values;

    /**
     * @param string[] $values
     */
    public function __construct(string $pathProperty, array $values)
    {
        $this->pathProperty = $pathProperty;
        $this->values = $values;
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
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
