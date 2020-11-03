<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security;

use Symfony\Component\Ldap\Security\LdapUser;

class LdapExtraFields
{
    /** @var string|null */
    private $email;
    /** @var string|null */
    private $displayName;
    /** @var string|null */
    private $givenName;
    /** @var string|null */
    private $lastName;

    public function __construct(?string $email, ?string $displayName, ?string $givenName, ?string $lastName)
    {
        $this->email = $email;
        $this->displayName = $displayName;
        $this->givenName = $givenName;
        $this->lastName = $lastName;
    }

    public function getEmail(LdapUser $user): string
    {
        return $this->extractParameter($user, $this->email);
    }

    public function getDisplayName(LdapUser $user): string
    {
        return $this->extractParameter($user, $this->displayName);
    }

    public function getGivenName(LdapUser $user): string
    {
        return $this->extractParameter($user, $this->givenName);
    }

    public function getLastName(LdapUser $user): string
    {
        return $this->extractParameter($user, $this->lastName);
    }

    private function extractParameter(LdapUser $user, ?string $param): string
    {
        return $user->getExtraFields()[$param] ?? '';
    }
}
