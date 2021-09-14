<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class NotEmpty implements ConditionInterface
{
    private string $pathProperty;

    public function __construct(string $pathProperty)
    {
        $this->pathProperty = $pathProperty;
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function valid($objectOrArray): bool
    {
        $accessor = new PropertyAccessor();
        $date = $accessor->getValue($objectOrArray, $this->pathProperty);

        return !empty($date);
    }
}
