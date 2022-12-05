<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data\Condition;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class InMyCircles implements ConditionInterface
{
    public function __construct(private readonly UserService $userService, private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function valid($objectOrArray): bool
    {
        if (!$objectOrArray instanceof Revision) {
            throw new \RuntimeException('Unexpected non revision object');
        }

        return $this->inMyCircles($objectOrArray->getCircles());
    }

    public function inMyCircles(mixed $circles): bool
    {
        if (\is_array($circles) && 0 === \count($circles)) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
            return true;
        }

        if (\is_array($circles)) {
            $user = $this->userService->getCurrentUser(UserService::DONT_DETACH);

            return \count(\array_intersect($circles, $user->getCircles())) > 0;
        }

        $user = $this->userService->getCurrentUser(UserService::DONT_DETACH);

        return \in_array($circles, $user->getCircles());
    }
}
