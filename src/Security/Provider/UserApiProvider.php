<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Provider;

use EMS\CoreBundle\Repository\AuthTokenRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserApiProvider implements UserProviderInterface
{
    private AuthTokenRepository $authTokenRepository;

    public function __construct(AuthTokenRepository $authTokenRepository)
    {
        $this->authTokenRepository = $authTokenRepository;
    }

    public function loadUserByUsername($username): UserInterface
    {
        $authToken = $this->authTokenRepository->findOneBy(['value' => $username]);
        $user = $authToken ? $authToken->getUser() : null;

        if (null === $user) {
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
