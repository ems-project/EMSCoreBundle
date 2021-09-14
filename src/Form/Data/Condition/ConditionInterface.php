<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

interface ConditionInterface
{
    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function valid($objectOrArray): bool;
}
