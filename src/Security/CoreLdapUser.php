<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security;

use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Roles;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Security\LdapUser as SymfonyLdapUser;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

final class CoreLdapUser implements SymfonyUserInterface, UserInterface
{
    /** @var \DateTime */
    private $created;
    /** @var array<string> */
    private $circles;
    /** @var string */
    private $displayName;
    /** @var string */
    private $email;
    /** @var bool */
    private $enabled;
    /** @var Entry */
    private $entry;
    /** @var string */
    private $givenName;
    /** @var string */
    private $lastName;
    /** @var \DateTime */
    private $modified;
    /** @var string|null */
    private $password;
    /** @var array<Role|string> */
    private $roles;
    /** @var string|null */
    private $salt;
    /** @var string */
    private $username;

    private function __construct()
    {
    }

    public function __toString(): string
    {
        return (string) $this->getUsername();
    }

    public static function fromLdap(SymfonyUserInterface $ldapUser, LdapExtraFields $extraFields): CoreLdapUser
    {
        if (!$ldapUser instanceof SymfonyLdapUser) {
            throw new \RuntimeException(\sprintf('Could not create ldap user. Instance should be of type %s', SymfonyLdapUser::class));
        }

        $now = new \DateTime('now');

        $user = new static();
        $user->circles = [];
        $user->created = $now;
        $user->displayName = $extraFields->getDisplayName($ldapUser);
        $user->enabled = true;
        $user->email = $extraFields->getEmail($ldapUser);
        $user->entry = $ldapUser->getEntry();
        $user->modified = $now;
        $user->givenName = $extraFields->getGivenName($ldapUser);
        $user->lastName = $extraFields->getLastName($ldapUser);
        $user->password = $ldapUser->getPassword();
        $user->roles = $ldapUser->getRoles();
        $user->salt = $ldapUser->getSalt();
        $user->username = $ldapUser->getUsername();

        return $user;
    }

    public function randomizePassword(): void
    {
        $this->password = \sha1(\random_bytes(10));
    }

    public function eraseCredentials(): void
    {
        $this->password = null;
    }

    /**
     * @return array<string>
     */
    public function getCircles(): array
    {
        return [];
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEntry(): Entry
    {
        return $this->entry;
    }

    public function getGivenName(): string
    {
        return $this->givenName;
    }

    public function getLastLogin(): \DateTime
    {
        return $this->getCreated();
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getLayoutBoxed(): bool
    {
        return false;
    }

    public function getModified(): \DateTime
    {
        return $this->getCreated();
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function getSidebarCollapse(): bool
    {
        return false;
    }

    public function getSidebarMini(): bool
    {
        return true;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function hasRole(string $role): bool
    {
        return \in_array(\strtoupper($role), $this->getRoles(), true);
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Roles::ROLE_SUPER_ADMIN);
    }

    /**
     * @param array<string> $circles
     */
    public function setCircles($circles): self
    {
        return $this;
    }

    public function setDisplayName($displayName): self
    {
        return $this;
    }

    public function setAllowedToConfigureWysiwyg($allowedToConfigureWysiwyg): self
    {
        return $this;
    }

    public function getAllowedToConfigureWysiwyg(): bool
    {
        return false;
    }

    public function setWysiwygProfile(WysiwygProfile $wysiwygProfile = null): self
    {
        return $this;
    }

    public function getWysiwygProfile(): WysiwygProfile
    {
        return new WysiwygProfile();
    }

    public function setWysiwygOptions($wysiwygOptions): self
    {
        return $this;
    }

    public function getWysiwygOptions(): ?string
    {
        return '';
    }

    public function setLayoutBoxed($layoutBoxed): self
    {
        return $this;
    }

    public function setSidebarMini($sidebarMini): self
    {
        return $this;
    }

    public function setEmailNotification($emailNotification): self
    {
        return $this;
    }

    public function getEmailNotification(): bool
    {
        return false;
    }

    public function setSidebarCollapse($sidebarCollapse): self
    {
        return $this;
    }

    public function addAuthToken(AuthToken $authToken): self
    {
        return $this;
    }

    public function removeAuthToken(AuthToken $authToken): self
    {
        return $this;
    }

    /**
     * @return iterable<AuthToken>
     */
    public function getAuthTokens(): iterable
    {
        return [];
    }

    /**
     * @return array{id: int|string, username:string, displayName:string, roles:array<string>, email:string, circles:array<string>, lastLogin: ?string}
     */
    public function toArray(): array
    {
        $roles = [];
        foreach ($this->getRoles() as $role) {
            if ($role instanceof Role) {
                $roles[] = $role->getRole();
            } else {
                $roles[] = $role;
            }
        }

        return [
            'id' => \sha1($this->getEntry()->getDn()),
            'username' => $this->getUsername(),
            'displayName' => $this->getDisplayName(),
            'roles' => $roles,
            'email' => $this->getEmail(),
            'circles' => $this->getCircles(),
            'lastLogin' => $this->getLastLogin()->format('c'),
        ];
    }
}
