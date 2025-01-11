<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class DateInFuture implements ConditionInterface
{
    public function __construct(private readonly string $pathProperty)
    {
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    #[\Override]
    public function valid($objectOrArray): bool
    {
        $accessor = new PropertyAccessor();
        $date = $accessor->getValue($objectOrArray, $this->pathProperty);

        if (empty($date)) {
            return false;
        }

        if ($date instanceof \DateTime) {
            return $date > new \DateTime('now');
        }

        throw new \RuntimeException('Unexpected error in data format');
    }
}
