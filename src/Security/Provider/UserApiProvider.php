<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Provider;

use EMS\CoreBundle\Repository\AuthTokenRepository;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<UserInterface>
 */
class UserApiProvider implements UserProviderInterface
{
    public function __construct(private readonly AuthTokenRepository $authTokenRepository)
    {
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $authToken = $this->authTokenRepository->findOneBy(['value' => $identifier]);
        $user = $authToken ? $authToken->getUser() : null;

        if (null === $user) {
            throw new UserNotFoundException($identifier);
        }

        if ($user->isExpired()) {
            throw new AccountExpiredException(\sprintf('The account "%s" is expired', $user->getUserIdentifier()));
        }
        if (!$user->isEnabled()) {
            throw new DisabledException(\sprintf('The account "%s" is disabled', $user->getUserIdentifier()));
        }

        return $user;
    }

    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException();
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return UserInterface::class === $class;
    }
}
