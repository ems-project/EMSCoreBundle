<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\LdapUser as SymfonyLdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

class CoreLdapUserProvider extends LdapUserProvider
{
    /** @var Registry */
    private $doctrine;
    /** @var LdapExtraFields */
    private $extraFields;
    /** @var UserService */
    private $userService;

    /**
     * @param array<string> $defaultRoles
     * @param array<string> $extraFields
     */
    public function __construct(Registry $doctrine, LdapExtraFields $extraFieldsService, UserService $userService, LdapInterface $ldap, string $baseDn, ?string $searchDn = null, ?string $searchPassword = null, array $defaultRoles = [], ?string $uidKey = null, ?string $filter = null, ?string $passwordAttribute = null, array $extraFields = [])
    {
        parent::__construct($ldap, $baseDn, $searchDn, $searchPassword, $defaultRoles, $uidKey, $filter, $passwordAttribute, $extraFields);
        $this->doctrine = $doctrine;
        $this->extraFields = $extraFieldsService;
        $this->userService = $userService;
    }

    /**
     * @param string $username
     */
    protected function loadUser($username, Entry $entry): UserInterface
    {
        $authenticatedUser = parent::loadUser($username, $entry);
        /** @var UserInterface|null $dbUser */
        $dbUser = $this->userService->getUser($username, false);

        if ($dbUser instanceof UserInterface) {
            return $dbUser;
        }

        $ldapUser = CoreLdapUser::fromLdap($authenticatedUser, $this->extraFields);
        $ldapUser->randomizePassword();
        $newUser = User::fromCoreLdap($ldapUser);

        $em = $this->doctrine->getEntityManager();
        $em->persist($newUser);
        $em->flush();

        return $newUser;
    }

    public function loadUserByUsername($username)
    {
        try {
            return parent::loadUserByUsername($username);
        } catch (\Exception $exception) {
            throw new UsernameNotFoundException();
        }
    }

    public function refreshUser(SymfonyUserInterface $user): SymfonyUserInterface
    {
        if ($user instanceof CoreLdapUser) {
            return CoreLdapUser::fromLdap(new SymfonyLdapUser($user->getEntry(), $user->getUsername(), $user->getPassword(), $user->getRoles()), $this->extraFields);
        }

        if (!$user instanceof UserInterface) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $refreshedUser = $this->userService->getUser($user->getUsername(), false);

        if (!$refreshedUser instanceof SymfonyUserInterface) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', \get_class($refreshedUser)));
        }

        return $refreshedUser;
    }

    public function supportsClass($class): bool
    {
        return CoreLdapUser::class === $class;
    }
}
