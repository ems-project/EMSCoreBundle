<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Roles;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Security\LdapUser as SymfonyLdapUser;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

final class CoreLdapUser implements SymfonyUserInterface, UserInterface
{
    private \DateTime $created;
    private string $displayName;
    private string $email;
    private Entry $entry;
    private string $givenName;
    private string $lastName;
    private \DateTime $modified;
    private ?string $password = null;
    /** @var string[] */
    private array $roles;
    private ?string $salt;
    private string $username;

    public function __construct(SymfonyUserInterface $ldapUser, LdapExtraFields $extraFields)
    {
        if (!$ldapUser instanceof SymfonyLdapUser) {
            throw new \RuntimeException(\sprintf('Could not create ldap user. Instance should be of type %s', SymfonyLdapUser::class));
        }

        $now = new \DateTime('now');
        $this->created = $now;
        $this->modified = $now;

        /** @var string[] $ldapRoles */
        $ldapRoles = $ldapUser->getRoles();

        $this->displayName = $extraFields->getDisplayName($ldapUser);
        $this->email = $extraFields->getEmail($ldapUser);
        $this->entry = $ldapUser->getEntry();
        $this->givenName = $extraFields->getGivenName($ldapUser);
        $this->lastName = $extraFields->getLastName($ldapUser);
        $this->password = $ldapUser->getPassword();
        $this->roles = $ldapRoles;
        $this->salt = $ldapUser->getSalt();
        $this->username = $ldapUser->getUsername();
    }

    public function __toString(): string
    {
        return $this->getUsername();
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
        return $this->modified;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @return string[]
     */
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
     * {@inheritDoc}
     */
    public function setCircles(array $circles): self
    {
        return $this;
    }

    public function setDisplayName(string $displayName): self
    {
        return $this;
    }

    public function setAllowedToConfigureWysiwyg(bool $allowedToConfigureWysiwyg): self
    {
        return $this;
    }

    public function getAllowedToConfigureWysiwyg(): ?bool
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

    public function setWysiwygOptions(?string $wysiwygOptions): self
    {
        return $this;
    }

    public function getWysiwygOptions(): ?string
    {
        return '';
    }

    public function setLayoutBoxed(bool $layoutBoxed): self
    {
        return $this;
    }

    public function setSidebarMini(bool $sidebarMini): self
    {
        return $this;
    }

    public function setEmailNotification(bool $emailNotification): self
    {
        return $this;
    }

    public function getEmailNotification(): bool
    {
        return false;
    }

    public function setSidebarCollapse(bool $sidebarCollapse): self
    {
        return $this;
    }

    public function addAuthToken(AuthToken $authToken): self
    {
        return $this;
    }

    public function removeAuthToken(AuthToken $authToken): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthTokens(): Collection
    {
        return new ArrayCollection();
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => \sha1($this->getEntry()->getDn()),
            'username' => $this->getUsername(),
            'displayName' => $this->getDisplayName(),
            'roles' => $this->getRoles(),
            'email' => $this->getEmail(),
            'circles' => $this->getCircles(),
            'lastLogin' => $this->getLastLogin()->format('c'),
        ];
    }
}
