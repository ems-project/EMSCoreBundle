<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class NotEmpty implements ConditionInterface
{
    /** @var string[] */
    private readonly array $pathProperties;

    public function __construct(string ...$pathProperties)
    {
        $this->pathProperties = $pathProperties;
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    #[\Override]
    public function valid($objectOrArray): bool
    {
        $accessor = new PropertyAccessor();
        foreach ($this->pathProperties as $pathProperty) {
            $date = $accessor->getValue($objectOrArray, $pathProperty);
            if (empty($date)) {
                return false;
            }
        }

        return true;
    }
}
