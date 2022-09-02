<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Ldap;

use EMS\CoreBundle\Entity\User;
use Symfony\Component\Ldap\Security\LdapUser;

final class LdapCoreUserFactory
{
    public static function create(LdapUser $ldapUser, LdapConfig $ldapConfig): User
    {
        $user = new User();
        $user->setUsername($ldapUser->getUserIdentifier());
        $user->setRoles($ldapUser->getRoles());
        $user->setEnabled(true);

        $password = $ldapUser->getPassword() ?? \sha1(\random_bytes(10));
        $user->setPassword($password);

        if ($salt = $ldapUser->getSalt()) {
            $user->setSalt($salt);
        }

        $ldapExtraFields = $ldapUser->getExtraFields();

        if ($email = self::getExtraField($ldapExtraFields, $ldapConfig->emailField)) {
            $user->setEmail($email);
        }
        if ($displayName = self::getExtraField($ldapExtraFields, $ldapConfig->displayNameField)) {
            $user->setDisplayName($displayName);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $extraFields
     */
    private static function getExtraField(array $extraFields, ?string $field): ?string
    {
        return $field ? $extraFields[$field] : null;
    }
}
