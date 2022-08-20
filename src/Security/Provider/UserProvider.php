<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Provider;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function loadUserByUsername($username): UserInterface
    {
        return $this->findUser($username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        return $this->findUser($user->getUsername());
    }

    public function supportsClass($class): bool
    {
        return User::class === $class;
    }

    private function findUser(string $usernameOrEmail): User
    {
        if (null === $user = $this->userRepository->findUserByUsernameOrEmail($usernameOrEmail)) {
            throw new UsernameNotFoundException(\sprintf('Username "%s" does not exists.', $usernameOrEmail));
        }

        return $user;
    }
}
