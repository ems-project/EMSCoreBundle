<?php

namespace EMS\CoreBundle\Security;

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\LdapUser as SymfonyLdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;

class CoreLdapUserProvider extends LdapUserProvider
{
    /** @var string */
    private $emailField;

    /**
     * @param array<string> $defaultRoles
     * @param array<string> $extraFields
     */
    public function __construct(string $emailField, LdapInterface $ldap, string $baseDn, string $searchDn = null, string $searchPassword = null, array $defaultRoles = [], string $uidKey = null, string $filter = null, string $passwordAttribute = null, array $extraFields = [])
    {
        parent::__construct($ldap, $baseDn, $searchDn, $searchPassword, $defaultRoles, $uidKey, $filter, $passwordAttribute, $extraFields);
        $this->emailField = $emailField;
    }

    /**
     * @param string $username
     */
    protected function loadUser($username, Entry $entry): CoreLdapUser
    {
        return CoreLdapUser::fromLdap(parent::loadUser($username, $entry), $this->emailField);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof CoreLdapUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        return CoreLdapUser::fromLdap(new SymfonyLdapUser($user->getEntry(), $user->getUsername(), $user->getPassword(), $user->getRoles()), $this->emailField);
    }

    public function supportsClass($class)
    {
        return CoreLdapUser::class === $class;
    }
}
