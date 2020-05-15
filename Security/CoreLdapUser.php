<?php

declare(strict_types = 1);

namespace EMS\CoreBundle\Security;

use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Entity\WysiwygProfile;
use FOS\UserBundle\Model\UserInterface as FOSUserInterface;
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
    private $email;
    /** @var bool */
    private $enabled;
    /** @var Entry */
    private $entry;
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

    public static function fromLdap(SymfonyUserInterface $ldapUser, string $emailField): CoreLdapUser
    {
        if (! $ldapUser instanceof SymfonyLdapUser) {
            throw new \RuntimeException(\sprintf('Could not create ldap user. Instance should be of type %s', SymfonyLdapUser::class));
        }

        $user = new static();
        $user->circles = [];
        $user->created = $user->modified = new \DateTime('now');
        $user->enabled = true;
        $user->email = $ldapUser->getExtraFields()[$emailField] ?? '';
        $user->entry = $ldapUser->getEntry();
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
        return $this->getUsername();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEntry(): Entry
    {
        return $this->entry;
    }

    public function getLastLogin(): \DateTime
    {
        return $this->getCreated();
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
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(FOSUserInterface::ROLE_SUPER_ADMIN);
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

    public function getWysiwygOptions(): string
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
}
