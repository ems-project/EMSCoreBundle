<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Api;

use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ApiUserProvider implements UserProviderInterface
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function loadUserByUsername($username): UserInterface
    {
        if (null === $user = $this->userService->findUserByApikey($username)) {
            throw new UsernameNotFoundException($username);
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException();
    }

    public function supportsClass($class): bool
    {
        return UserInterface::class === $class;
    }
}
