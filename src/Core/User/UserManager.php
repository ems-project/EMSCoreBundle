<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use FOS\UserBundle\Model\UserManagerInterface as FosUserManager;

final class UserManager
{
    private TokenStorageInterface $tokenStorage;
    private FosUserManager $fosUserManager;

    public function __construct(TokenStorageInterface $tokenStorage, FosUserManager $fosUserManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->fosUserManager = $fosUserManager;
    }

    public function getAuthenticatedUser(): UserInterface
    {
        $token = $this->getToken();
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Invalid user!');
        }

        return  $user;
    }

    /**
     * @param UserInterface|User $user
     */
    public function update(User $user): void
    {
        $this->fosUserManager->updateUser($user);
    }

    private function getToken(): TokenInterface
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            throw new \RuntimeException('Token is null, could not get the currentUser from token.');
        }

        return $token;
    }
}