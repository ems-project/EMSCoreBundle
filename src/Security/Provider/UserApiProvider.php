<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Provider;

use EMS\CoreBundle\Repository\AuthTokenRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserApiProvider implements UserProviderInterface
{
    public function __construct(private readonly AuthTokenRepository $authTokenRepository)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $authToken = $this->authTokenRepository->findOneBy(['value' => $identifier]);
        $user = $authToken ? $authToken->getUser() : null;

        if (null === $user) {
            throw new UserNotFoundException($identifier);
        }

        return $user;
    }

    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException();
    }

    public function supportsClass(string $class): bool
    {
        return UserInterface::class === $class;
    }
}
