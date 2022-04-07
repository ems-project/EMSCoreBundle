<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Security\CoreLdapUser;
use FOS\UserBundle\Model\User as BaseUser;

/**
 * @ORM\Entity
 * @ORM\Table(name="`user`")
 * @ORM\HasLifecycleCallbacks()
 */
class User extends BaseUser implements UserInterface, EntityInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var array
     *
     * @ORM\Column(name="circles", type="json_array", nullable=true)
     */
    private $circles;

    /**
     * @var string
     *
     * @ORM\Column(name="display_name", type="string", length=255, nullable=true)
     */
    private $displayName;

    /**
     * @var bool
     *
     * @ORM\Column(name="allowed_to_configure_wysiwyg", type="boolean", nullable=true)
     */
    private $allowedToConfigureWysiwyg;

    /**
     * @var WysiwygProfile
     *
     * @ORM\ManyToOne(targetEntity="EMS\CoreBundle\Entity\WysiwygProfile", cascade={})
     * @ORM\JoinColumn(name="wysiwyg_profile_id", referencedColumnName="id")
     */
    private $wysiwygProfile;

    /**
     * @var string
     *
     * @ORM\Column(name="wysiwyg_options", type="text", nullable=true)
     */
    private $wysiwygOptions;

    /**
     * @ORM\Column(name="settings", type="text", nullable=true)
     */
    private ?string $settings = null;

    /**
     * @var bool
     *
     * @ORM\Column(name="layout_boxed", type="boolean")
     */
    private $layoutBoxed;

    /**
     * @ORM\Column(name="email_notification", type="boolean")
     */
    private bool $emailNotification = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="sidebar_mini", type="boolean")
     */
    private $sidebarMini;

    /**
     * @var bool
     *
     * @ORM\Column(name="sidebar_collapse", type="boolean")
     */
    private $sidebarCollapse;

    /**
     * @var Collection<int,AuthToken>
     *
     * @ORM\OneToMany(targetEntity="AuthToken", mappedBy="user", cascade={"remove"})
     * @ORM\OrderBy({"created" = "ASC"})
     */
    private $authTokens;

    /**
     * @ORM\Column(name="locale", type="string", nullable=false)
     */
    private string $locale;

    /**
     * @ORM\Column(name="locale_preferred", type="string", nullable=true)
     */
    private ?string $localePreferred = null;

    private const DEFAULT_LOCALE = 'en';

    public function __construct()
    {
        parent::__construct();

        $this->layoutBoxed = false;
        $this->sidebarCollapse = false;
        $this->sidebarMini = true;
        $this->authTokens = new ArrayCollection();
        $this->locale = self::DEFAULT_LOCALE;
    }

    public function __clone()
    {
        $this->authTokens = new ArrayCollection();
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
    }

    public static function fromCoreLdap(CoreLdapUser $ldapUser): self
    {
        $user = new self();
        $user->username = $ldapUser->getUsername();
        $user->roles = $ldapUser->getRoles();
        $user->created = $user->modified = new \DateTime('now');
        $user->circles = [];
        $user->enabled = true;
        $user->email = $ldapUser->getEmail();
        $user->displayName = $ldapUser->getDisplayName();
        $user->password = \sha1(\random_bytes(10));

        return $user;
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
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Get modified.
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Get circles.
     *
     * @return array
     */
    public function getCircles()
    {
        return $this->circles;
    }

    /**
     * Get expiresAt.
     *
     * @return \DateTime
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return User
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Set modified.
     *
     * @param \DateTime $modified
     *
     * @return User
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Set circles.
     *
     * @param array $circles
     *
     * @return User
     */
    public function setCircles($circles)
    {
        $this->circles = $circles;

        return $this;
    }

    /**
     * Set displayName.
     *
     * @param string $displayName
     *
     * @return User
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * Get displayName.
     *
     * @return string
     */
    public function getDisplayName()
    {
        if (empty($this->displayName)) {
            return $this->getUsername();
        }

        return $this->displayName;
    }

    /**
     * Set allowedToConfigureWysiwyg.
     *
     * @param bool $allowedToConfigureWysiwyg
     *
     * @return User
     */
    public function setAllowedToConfigureWysiwyg($allowedToConfigureWysiwyg)
    {
        $this->allowedToConfigureWysiwyg = $allowedToConfigureWysiwyg;

        return $this;
    }

    /**
     * Get allowedToConfigureWysiwyg.
     *
     * @return bool
     */
    public function getAllowedToConfigureWysiwyg()
    {
        return $this->allowedToConfigureWysiwyg;
    }

    /**
     * Set wysiwygProfile.
     *
     * @return User
     */
    public function setWysiwygProfile(WysiwygProfile $wysiwygProfile)
    {
        $this->wysiwygProfile = $wysiwygProfile;

        return $this;
    }

    public function getWysiwygProfile(): ?WysiwygProfile
    {
        return $this->wysiwygProfile;
    }

    /**
     * Set wysiwygOptions.
     *
     * @param string $wysiwygOptions
     *
     * @return User
     */
    public function setWysiwygOptions($wysiwygOptions)
    {
        $this->wysiwygOptions = $wysiwygOptions;

        return $this;
    }

    /**
     * Get wysiwygOptions.
     *
     * @return string
     */
    public function getWysiwygOptions(): ?string
    {
        return $this->wysiwygOptions;
    }

    public function setSettings(?string $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function getSettings(): ?string
    {
        return $this->settings;
    }

    /**
     * Set layoutBoxed.
     *
     * @param bool $layoutBoxed
     *
     * @return User
     */
    public function setLayoutBoxed($layoutBoxed)
    {
        $this->layoutBoxed = $layoutBoxed;

        return $this;
    }

    /**
     * Get layoutBoxed.
     *
     * @return bool
     */
    public function getLayoutBoxed()
    {
        return $this->layoutBoxed;
    }

    /**
     * Set sidebarMini.
     *
     * @param bool $sidebarMini
     *
     * @return User
     */
    public function setSidebarMini($sidebarMini)
    {
        $this->sidebarMini = $sidebarMini;

        return $this;
    }

    /**
     * Get sidebarMini.
     *
     * @return bool
     */
    public function getSidebarMini()
    {
        return $this->sidebarMini;
    }

    /**
     * Set sidebarCollapse.
     *
     * @param bool $sidebarCollapse
     *
     * @return User
     */
    public function setSidebarCollapse($sidebarCollapse)
    {
        $this->sidebarCollapse = $sidebarCollapse;

        return $this;
    }

    /**
     * Get sidebarCollapse.
     *
     * @return bool
     */
    public function getSidebarCollapse()
    {
        return $this->sidebarCollapse;
    }

    /**
     * Add authToken.
     *
     * @return User
     */
    public function addAuthToken(AuthToken $authToken)
    {
        $this->authTokens[] = $authToken;

        return $this;
    }

    /**
     * Remove authToken.
     */
    public function removeAuthToken(AuthToken $authToken)
    {
        $this->authTokens->removeElement($authToken);
    }

    /**
     * Get authTokens.
     *
     * @return Collection
     */
    public function getAuthTokens()
    {
        return $this->authTokens;
    }

    /**
     * Set emailNotification.
     *
     * @param bool $emailNotification
     *
     * @return User
     */
    public function setEmailNotification($emailNotification)
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
}
