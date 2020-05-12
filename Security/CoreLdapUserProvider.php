<?php

namespace EMS\CoreBundle\Security;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\UserService;
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

    /** @var UserService */
    private $userService;

    /**
     * @param array<string> $defaultRoles
     * @param array<string> $extraFields
     */
    public function __construct(string $emailField, UserService $userService, LdapInterface $ldap, string $baseDn, string $searchDn = null, string $searchPassword = null, array $defaultRoles = [], string $uidKey = null, string $filter = null, string $passwordAttribute = null, array $extraFields = [])
    {
        parent::__construct($ldap, $baseDn, $searchDn, $searchPassword, $defaultRoles, $uidKey, $filter, $passwordAttribute, $extraFields);
        $this->emailField = $emailField;
        $this->userService = $userService;
    }

    /**
     * @param string $username
     */
    protected function loadUser($username, Entry $entry): UserInterface
    {
        $authenticatedUser = parent::loadUser($username, $entry);
        $dbUser = $this->userService->getUser($username, false);

        if (!$dbUser instanceof UserInterface) {
            return CoreLdapUser::fromLdap($authenticatedUser, $this->emailField);
        }

        return $dbUser;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($user instanceof CoreLdapUser) {
            return CoreLdapUser::fromLdap(new SymfonyLdapUser($user->getEntry(), $user->getUsername(), $user->getPassword(), $user->getRoles()), $this->emailField);
        }

        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $refreshedUser = $this->userService->getUser($user->getUsername(), false);

        if (!$refreshedUser instanceof UserInterface) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($refreshedUser)));
        }

        return $refreshedUser;
    }

    public function supportsClass($class)
    {
        return CoreLdapUser::class === $class;
    }
}
