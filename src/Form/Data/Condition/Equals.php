<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class Equals implements ConditionInterface
{
    public function __construct(
        private readonly string $pathProperty,
        private readonly mixed $value,
    ) {
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    #[\Override]
    public function valid($objectOrArray): bool
    {
        return $this->value === (new PropertyAccessor())->getValue($objectOrArray, $this->pathProperty);
    }
}
