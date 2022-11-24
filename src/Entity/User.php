<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Core\User\UserOptions;
use EMS\CoreBundle\Roles;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Type;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="`user`")
 * @ORM\HasLifecycleCallbacks()
 */
class User implements UserInterface, EntityInterface, PasswordAuthenticatedUserInterface
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private ?int $id = null;

    /**
     * @var ?string[]
     *
     * @ORM\Column(name="circles", type="json", nullable=true)
     */
    private ?array $circles;

    /**
     * @ORM\Column(name="display_name", type="string", length=255, nullable=true)
     */
    private ?string $displayName = null;

    /**
     * @ORM\Column(name="allowed_to_configure_wysiwyg", type="boolean", nullable=true)
     */
    private ?bool $allowedToConfigureWysiwyg = null;

    /**
     * @ORM\ManyToOne(targetEntity="EMS\CoreBundle\Entity\WysiwygProfile", cascade={})
     * @ORM\JoinColumn(name="wysiwyg_profile_id", referencedColumnName="id")
     */
    private ?WysiwygProfile $wysiwygProfile = null;

    /**
     * @ORM\Column(name="wysiwyg_options", type="text", nullable=true)
     */
    private ?string $wysiwygOptions = null;

    /**
     * @ORM\Column(name="layout_boxed", type="boolean")
     */
    private bool $layoutBoxed = false;

    /**
     * @ORM\Column(name="email_notification", type="boolean")
     */
    private bool $emailNotification = true;

    /**
     * @ORM\Column(name="sidebar_mini", type="boolean")
     */
    private bool $sidebarMini = false;

    /**
     * @ORM\Column(name="sidebar_collapse", type="boolean")
     */
    private bool $sidebarCollapse = false;

    /**
     * @var Collection<int,AuthToken>
     *
     * @ORM\OneToMany(targetEntity="AuthToken", mappedBy="user", cascade={"remove"})
     * @ORM\OrderBy({"created" = "ASC"})
     */
    private $authTokens;

    /**
     * @ORM\Column(name="locale", type="string", nullable=false, options={"default":"en"})
     */
    private string $locale;

    /**
     * @ORM\Column(name="locale_preferred", type="string", nullable=true)
     */
    private ?string $localePreferred = null;

    /**
     * @ORM\Column(name="username", type="string", length=180)
     */
    private ?string $username = null;

    /**
     * @ORM\Column(name="username_canonical", type="string", length=180, unique=true)
     */
    private ?string $usernameCanonical = null;

    /**
     * @ORM\Column(name="email", type="string", length=180)
     */
    private ?string $email = null;

    /**
     * @ORM\Column(name="email_canonical", type="string", length=180, unique=true)
     */
    private ?string $emailCanonical = null;

    /**
     * @ORM\Column(name="enabled", type="boolean")
     */
    private bool $enabled;

    /**
     * @ORM\Column(name="salt", type="string", nullable=true)
     */
    private ?string $salt = null;

    /**
     * @ORM\Column(name="password", type="string")
     */
    private ?string $password = null;
    private ?string $plainPassword = null;

    /**
     * @ORM\Column(name="last_login", type="datetime", nullable=true)
     */
    private ?\DateTime $lastLogin = null;

    /**
     * @ORM\Column(name="confirmation_token", type="string", length=180, unique=true, nullable=true)
     */
    private ?string $confirmationToken = null;

    /**
     * @ORM\Column(name="password_requested_at", type="datetime", nullable=true)
     */
    private ?\DateTime $passwordRequestedAt = null;

    /**
     * @var string[]
     *
     * @ORM\Column(name="roles", type="array")
     */
    private array $roles;

    /**
     * @var ?array<string, bool>
     *
     * @ORM\Column(name="user_options", type="json", nullable=true)
     */
    protected ?array $userOptions = [];

    private const DEFAULT_LOCALE = 'en';

    public function __construct()
    {
        $this->authTokens = new ArrayCollection();
        $this->enabled = false;
        $this->roles = [];
        $this->circles = [];
        $this->locale = self::DEFAULT_LOCALE;

        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function __clone()
    {
        $this->authTokens = new ArrayCollection();
    }

    /**
     * @return array{id: int|string, username:string, displayName:string, roles:array<string>, email:string, circles:array<string>, lastLogin: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'username' => $this->getUsername(),
            'displayName' => $this->getDisplayName(),
            'roles' => $this->getRoles(),
            'email' => $this->getEmail(),
            'circles' => $this->getCircles(),
            'lastLogin' => null !== $this->getLastLogin() ? $this->getLastLogin()->format('c') : null,
            'locale' => $this->getLocale(),
            'localePreferred' => $this->getLocalePreferred(),
        ];
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

    /**
     * {@inheritDoc}
     */
    public function getCircles(): array
    {
        return $this->circles ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function setCircles(array $circles): self
    {
        $this->circles = $circles;

        return $this;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getDisplayName(): string
    {
        if (empty($this->displayName)) {
            return $this->getUsername();
        }

        return $this->displayName;
    }

    public function setAllowedToConfigureWysiwyg(bool $allowedToConfigureWysiwyg): self
    {
        $this->allowedToConfigureWysiwyg = $allowedToConfigureWysiwyg;

        return $this;
    }

    public function getAllowedToConfigureWysiwyg(): ?bool
    {
        return $this->allowedToConfigureWysiwyg;
    }

    public function setWysiwygProfile(?WysiwygProfile $wysiwygProfile): self
    {
        $this->wysiwygProfile = $wysiwygProfile;

        return $this;
    }

    public function getWysiwygProfile(): ?WysiwygProfile
    {
        return $this->wysiwygProfile;
    }

    public function setWysiwygOptions(?string $wysiwygOptions): self
    {
        $this->wysiwygOptions = $wysiwygOptions;

        return $this;
    }

    public function getWysiwygOptions(): ?string
    {
        return $this->wysiwygOptions;
    }

    public function setLayoutBoxed(bool $layoutBoxed): self
    {
        $this->layoutBoxed = $layoutBoxed;

        return $this;
    }

    public function getLayoutBoxed(): bool
    {
        return $this->layoutBoxed;
    }

    public function setSidebarMini(bool $sidebarMini): self
    {
        $this->sidebarMini = $sidebarMini;

        return $this;
    }

    public function getSidebarMini(): bool
    {
        return $this->sidebarMini;
    }

    public function setSidebarCollapse(bool $sidebarCollapse): self
    {
        $this->sidebarCollapse = $sidebarCollapse;

        return $this;
    }

    public function getSidebarCollapse(): bool
    {
        return $this->sidebarCollapse;
    }

    public function addAuthToken(AuthToken $authToken): self
    {
        $this->authTokens[] = $authToken;

        return $this;
    }

    public function removeAuthToken(AuthToken $authToken): void
    {
        $this->authTokens->removeElement($authToken);
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthTokens(): Collection
    {
        return $this->authTokens;
    }

    public function setEmailNotification(bool $emailNotification): self
    {
        $this->emailNotification = $emailNotification;

        return $this;
    }

    public function getEmailNotification(): bool
    {
        return $this->emailNotification;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

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
        ]);
    }

    public function unserialize(string $serialized): void
    {
        $data = \unserialize($serialized);

        if (13 === \count($data)) {
            // Unserializing a User object from 1.3.x
            unset($data[4], $data[5], $data[6], $data[9], $data[10]);
            $data = \array_values($data);
        } elseif (11 === \count($data)) {
            // Unserializing a User from a dev version somewhere between 2.0-alpha3 and 2.0-beta1
            unset($data[4], $data[7], $data[8]);
            $data = \array_values($data);
        }

        list(
            $this->password,
            $this->salt,
            $this->usernameCanonical,
            $this->username,
            $this->enabled,
            $this->id,
            $this->email,
            $this->emailCanonical
        ) = $data;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getId(): int
    {
        return Type::integer($this->id);
    }

    public function getUserIdentifier(): string
    {
        return $this->username ?? '';
    }

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

    public function getEmail(): string
    {
        return $this->email ?? '';
    }

    public function getEmailCanonical(): ?string
    {
        return $this->emailCanonical;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

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
    public function getRoles(): array
    {
        $roles = $this->roles;

        // we need to make sure to have at least one role
        $roles[] = Roles::ROLE_USER;

        return \array_unique($roles);
    }

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

    public function setLastLogin(\DateTime $time = null): void
    {
        $this->lastLogin = $time;
    }

    public function setConfirmationToken(?string $confirmationToken): void
    {
        $this->confirmationToken = $confirmationToken;
    }

    public function setPasswordRequestedAt(\DateTime $date = null): void
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
