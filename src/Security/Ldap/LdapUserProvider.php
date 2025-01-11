<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Ldap;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\UserRepository;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider as SymfonyLdapUserProvider;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class LdapUserProvider extends SymfonyLdapUserProvider
{
    public function __construct(LdapInterface $ldap, private readonly LdapConfig $ldapConfig, private readonly UserRepository $userRepository)
    {
        parent::__construct($ldap,
            $ldapConfig->baseDn,
            $ldapConfig->searchDn,
            $ldapConfig->searchPassword,
            $ldapConfig->defaultRoles,
            $ldapConfig->uidKey,
            $ldapConfig->filter,
            $ldapConfig->passwordAttribute,
            $ldapConfig->extraFields
        );
    }

    #[\Override]
    protected function loadUser(string $identifier, Entry $entry): User
    {
        try {
            /** @var LdapUser $ldapUser */
            $ldapUser = parent::loadUser($identifier, $entry);
        } catch (\Throwable $e) {
            throw new UserNotFoundException(\sprintf('Ldap user not found (%s)', $e->getMessage()));
        }

        $user = $this->userRepository->findUserByUsernameOrEmail($ldapUser->getUserIdentifier());

        return $user ?: LdapCoreUserFactory::create($ldapUser, $this->ldapConfig);
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ('' === $this->ldapConfig->baseDn) {
            throw new UserNotFoundException(\sprintf('Ldap server not configured'));
        }

        return parent::loadUserByIdentifier($identifier);
    }
}
