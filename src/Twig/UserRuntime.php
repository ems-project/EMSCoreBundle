<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Service\UserService;
use Twig\Extension\RuntimeExtensionInterface;
use EMS\CoreBundle\Entity\UserInterface;

class UserRuntime implements RuntimeExtensionInterface
{
    /** @var UserService */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @param string $role
     * @param array<string> $circles
     * @return array<UserInterface>
     */
    public function getUsersForRoleAndCircles(string $role, array $circles): array
    {
        return $this->userService->getUsersForRoleAndCircles($role, $circles);
    }
}
