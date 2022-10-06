<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Ldap;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\UserRepository;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider as SymfonyLdapUserProvider;

class LdapUserProvider extends SymfonyLdapUserProvider
{
    private UserRepository $userRepository;
    private LdapConfig $ldapConfig;

    public function __construct(LdapInterface $ldap, LdapConfig $ldapConfig, UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
        $this->ldapConfig = $ldapConfig;

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

    protected function loadUser(string $identifier, Entry $entry): User
    {
        /** @var LdapUser $ldapUser */
        $ldapUser = parent::loadUser($identifier, $entry);
        $user = $this->userRepository->findUserByUsernameOrEmail($ldapUser->getUserIdentifier());

        return $user ?: LdapCoreUserFactory::create($ldapUser, $this->ldapConfig);
    }
}
