<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Provider;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<UserInterface>
 */
class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->findUser($identifier);
    }

    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->findUser($user->getUsername());
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }

    private function findUser(string $usernameOrEmail): User
    {
        if (null === $user = $this->userRepository->findUserByUsernameOrEmail($usernameOrEmail)) {
            throw new UserNotFoundException(\sprintf('Username "%s" does not exists.', $usernameOrEmail));
        }

        if ($user->isExpired()) {
            throw new AccountExpiredException(\sprintf('The account "%s" is expired', $user->getUserIdentifier()));
        }

        return $user;
    }
}
