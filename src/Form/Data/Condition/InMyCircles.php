<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\UserService;

class InMyCircles implements ConditionInterface
{
    public function __construct(private readonly UserService $userService)
    {
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    #[\Override]
    public function valid($objectOrArray): bool
    {
        if (!$objectOrArray instanceof Revision) {
            throw new \RuntimeException('Unexpected non revision object');
        }

        return $this->userService->inMyCircles($objectOrArray->getCircles());
    }
}
