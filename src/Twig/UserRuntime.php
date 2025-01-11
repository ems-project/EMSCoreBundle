<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\User\UserList;
use EMS\CoreBundle\Repository\UserRepository;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class UserRuntime implements RuntimeExtensionInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function getUsersEnabled(): UserList
    {
        return $this->userRepository->getUsersEnabled();
    }
}
