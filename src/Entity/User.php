<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Core\User\UserOptions;
use EMS\CoreBundle\Roles;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Locale;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class User implements UserInterface, EntityInterface, PasswordAuthenticatedUserInterface, \Stringable
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    /** @var ?string[] */
    private ?array $circles = [];
    private ?string $displayName = null;
    private ?WysiwygProfile $wysiwygProfile = null;
    private bool $layoutBoxed = false;
    private bool $emailNotification = true;
    private bool $sidebarMini = false;
    private bool $sidebarCollapse = false;
    /** @var Collection<int,AuthToken> */
    private Collection $authTokens;
    private string $locale = self::DEFAULT_LOCALE;
    private ?string $localePreferred = null;
    private ?string $username = null;
    private ?string $usernameCanonical = null;
    private ?string $email = null;
    private ?string $emailCanonical = null;
    private bool $enabled = false;
    private ?string $salt = null;
    private ?string $password = null;
    private ?string $plainPassword = null;
    private ?\DateTime $lastLogin = null;
    private ?\DateTime $expirationDate = null;
    private ?string $confirmationToken = null;
    private ?\DateTime $passwordRequestedAt = null;
    /** @var string[] */
    private array $roles = [];
    /** @var ?array<string, mixed> */
    protected ?array $userOptions = [];
    public const DEFAULT_LOCALE = 'en';

    public function __construct()
    {
        $this->authTokens = new ArrayCollection();

        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function __clone()
    {
        $this->authTokens = new ArrayCollection();
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'username' => $this->getUsername(),
            'displayName' => $this->getDisplayName(),
            'roles' => $this->getRoles(),
            'email' => $this->getEmail(),
            'circles' => $this->getCircles(),
            'lastLogin' => $this->getLastLogin()?->format('c'),
            'expirationDate' => $this->getExpirationDate()?->format('c'),
            'language' => $this->getLanguage(),
            'locale' => $this->getLocale(),
            'localePreferred' => $this->getLocalePreferred(),
            'userOptions' => $this->userOptions,
        ];
    }

    #[\Override]
    public function isExpired(): bool
    {
        if (null === $this->expirationDate) {
            return false;
        }

        $now = new \DateTime('now');

        return $now > $this->expirationDate;
    }

    public function getLanguage(): string
    {
        if ($this->localePreferred) {
            return Locale::getLanguage($this->localePreferred, self::DEFAULT_LOCALE);
        }

        return $this->getLocale();
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getLocalePreferred(): ?string
    {
        return $this->localePreferred;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function setLocalePreferred(?string $localePreferred): void
    {
        $this->localePreferred = $localePreferred;
    }

    #[\Override]
    public function getCircles(): array
    {
        return $this->circles ?? [];
    }

    #[\Override]
    public function getExpirationDate(): ?\DateTime
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTime $time = null): void
    {
        $this->expirationDate = $time;
    }

    #[\Override]
    public function setCircles(?array $circles): self
    {
        $this->circles = $circles;

        return $this;
    }

    #[\Override]
    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    #[\Override]
    public function getDisplayName(): string
    {
        if (empty($this->displayName)) {
            return $this->getUsername();
        }

        return $this->displayName;
    }

    #[\Override]
    public function setWysiwygProfile(?WysiwygProfile $wysiwygProfile): self
    {
        $this->wysiwygProfile = $wysiwygProfile;

        return $this;
    }

    #[\Override]
    public function getWysiwygProfile(): ?WysiwygProfile
    {
        return $this->wysiwygProfile;
    }

    #[\Override]
    public function setLayoutBoxed(bool $layoutBoxed): self
    {
        $this->layoutBoxed = $layoutBoxed;

        return $this;
    }

    #[\Override]
    public function getLayoutBoxed(): bool
    {
        return $this->layoutBoxed;
    }

    #[\Override]
    public function setSidebarMini(bool $sidebarMini): self
    {
        $this->sidebarMini = $sidebarMini;

        return $this;
    }

    #[\Override]
    public function getSidebarMini(): bool
    {
        return $this->sidebarMini;
    }

    #[\Override]
    public function setSidebarCollapse(bool $sidebarCollapse): self
    {
        $this->sidebarCollapse = $sidebarCollapse;

        return $this;
    }

    #[\Override]
    public function getSidebarCollapse(): bool
    {
        return $this->sidebarCollapse;
    }

    #[\Override]
    public function addAuthToken(AuthToken $authToken): self
    {
        $this->authTokens[] = $authToken;

        return $this;
    }

    #[\Override]
    public function removeAuthToken(AuthToken $authToken): void
    {
        $this->authTokens->removeElement($authToken);
    }

    #[\Override]
    public function getAuthTokens(): Collection
    {
        return $this->authTokens;
    }

    #[\Override]
    public function setEmailNotification(bool $emailNotification): self
    {
        $this->emailNotification = $emailNotification;

        return $this;
    }

    #[\Override]
    public function getEmailNotification(): bool
    {
        return $this->emailNotification;
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->getDisplayName();
    }

    public function isPasswordRequestNonExpired(int $ttl): bool
    {
        if (null === $this->passwordRequestedAt) {
            return false;
        }

        return ($this->passwordRequestedAt->getTimestamp() + $ttl) > \time();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->getUsername();
    }

    public function addRole(string $role): void
    {
        $role = \strtoupper($role);
        if (Roles::ROLE_USER === $role) {
            return;
        }

        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    public function serialize(): string
    {
        return \serialize([
            $this->password,
            $this->salt,
            $this->usernameCanonical,
            $this->username,
            $this->enabled,
            $this->id,
            $this->email,
            $this->emailCanonical,
            $this->expirationDate,
        ]);
    }

    public function unserialize(string $serialized): void
    {
        $data = \unserialize($serialized);

        if (13 === (\is_countable($data) ? \count($data) : 0)) {
            // Unserializing a User object from 1.3.x
            unset($data[4], $data[5], $data[6], $data[9], $data[10]);
            $data = \array_values($data);
        } elseif (11 === (\is_countable($data) ? \count($data) : 0)) {
            // Unserializing a User from a dev version somewhere between 2.0-alpha3 and 2.0-beta1
            unset($data[4], $data[7], $data[8]);
            $data = \array_values($data);
        }

        [$this->password, $this->salt, $this->usernameCanonical, $this->username, $this->enabled, $this->id, $this->email, $this->emailCanonical] = $data;
    }

    #[\Override]
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->username ?? '';
    }

    #[\Override]
    public function getUsername(): string
    {
        return $this->username ?? '';
    }

    public function getUsernameCanonical(): ?string
    {
        return $this->usernameCanonical;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    #[\Override]
    public function getEmail(): string
    {
        return $this->email ?? '';
    }

    public function getEmailCanonical(): ?string
    {
        return $this->emailCanonical;
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    #[\Override]
    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getRoles(): array
    {
        $roles = $this->roles;

        // we need to make sure to have at least one role
        $roles[] = Roles::ROLE_USER;

        return \array_unique($roles);
    }

    #[\Override]
    public function hasRole(string $role): bool
    {
        return \in_array(\strtoupper($role), $this->getRoles(), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Roles::ROLE_SUPER_ADMIN);
    }

    public function removeRole(string $role): void
    {
        if (false !== $key = \array_search(\strtoupper($role), $this->roles, true)) {
            unset($this->roles[$key]);
            $this->roles = \array_values($this->roles);
        }
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function setUsernameCanonical(?string $usernameCanonical): void
    {
        $this->usernameCanonical = $usernameCanonical;
    }

    public function setSalt(string $salt): void
    {
        $this->salt = $salt;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function setEmailCanonical(?string $emailCanonical): void
    {
        $this->emailCanonical = $emailCanonical;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function setSuperAdmin(bool $superAdmin): void
    {
        if ($superAdmin) {
            $this->addRole(Roles::ROLE_SUPER_ADMIN);
        } else {
            $this->removeRole(Roles::ROLE_SUPER_ADMIN);
        }
    }

    public function setPlainPassword(?string $password): void
    {
        $this->plainPassword = $password;
    }

    public function setLastLogin(?\DateTime $time = null): void
    {
        $this->lastLogin = $time;
    }

    public function setConfirmationToken(?string $confirmationToken): void
    {
        $this->confirmationToken = $confirmationToken;
    }

    public function setPasswordRequestedAt(?\DateTime $date = null): void
    {
        $this->passwordRequestedAt = $date;
    }

    public function getPasswordRequestedAt(): ?\DateTime
    {
        return $this->passwordRequestedAt;
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = [];

        foreach ($roles as $role) {
            $this->addRole($role);
        }

        return $this;
    }

    public function getUserOptions(): UserOptions
    {
        return new UserOptions($this->userOptions ?? []);
    }

    public function setUserOptions(UserOptions $userOptions): void
    {
        $this->userOptions = $userOptions->getOptions();
    }
}
