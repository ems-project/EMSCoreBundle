<?php

namespace EMS\CoreBundle\Entity;

use EMS\CoreBundle\Security\CoreLdapUser;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="`user`")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\UserRepository")
 * @ORM\HasLifecycleCallbacks()
 */
final class User extends BaseUser implements UserInterface
{
    public static function fromLdap(SymfonyUserInterface $ldapUser, string $emailField): CoreLdapUser;

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
     * @var bool
     *
     * @ORM\Column(name="layout_boxed", type="boolean")
     */
    private $layoutBoxed;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="email_notification", type="boolean")
     */
    private $emailNotification = true;

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

//     /**
//      * @ORM\OneToMany(targetEntity="AuthToken", mappedBy="user", cascade={"remove"})
//      * @ORM\OrderBy({"created" = "ASC"})
//      */
//     private $authTokens;

    
    public function __construct()
    {
        parent::__construct();
        
        $this->layoutBoxed = false;
        $this->sidebarCollapse = false;
        $this->sidebarMini = true;
        // your own logic
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
        $user = new static();
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
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }
    
    /**
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Get circles
     *
     * @return array
     */
    public function getCircles()
    {
        return $this->circles;
    }
    
    /**
     * Get expiresAt
     *
     * @return \DateTime
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }
    
    /**
     * Set created
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
     * Set modified
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
     * Set circles
     *
     * @param array $circles
     *
     * @return Userinterface
     */
    public function setCircles($circles)
    {
        $this->circles = $circles;
    
        return $this;
    }

    /**
     * Set displayName
     *
     * @param string $displayName
     *
     * @return Userinterface
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * Get displayName
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
     * Set allowedToConfigureWysiwyg
     *
     * @param boolean $allowedToConfigureWysiwyg
     *
     * @return Userinterface
     */
    public function setAllowedToConfigureWysiwyg($allowedToConfigureWysiwyg)
    {
        $this->allowedToConfigureWysiwyg = $allowedToConfigureWysiwyg;

        return $this;
    }

    /**
     * Get allowedToConfigureWysiwyg
     *
     * @return boolean
     */
    public function getAllowedToConfigureWysiwyg()
    {
        return $this->allowedToConfigureWysiwyg;
    }

    /**
     * Set wysiwygProfile
     *
     * @param WysiwygProfile $wysiwygProfile
     *
     * @return Userinterface
     */
    public function setWysiwygProfile(WysiwygProfile $wysiwygProfile)
    {
        $this->wysiwygProfile = $wysiwygProfile;

        return $this;
    }

    /**
     * Get wysiwygProfile
     *
     * @return WysiwygProfile
     */
    public function getWysiwygProfile()
    {
        return $this->wysiwygProfile;
    }

    /**
     * Set wysiwygOptions
     *
     * @param string $wysiwygOptions
     *
     * @return Userinterface
     */
    public function setWysiwygOptions($wysiwygOptions)
    {
        $this->wysiwygOptions = $wysiwygOptions;

        return $this;
    }

    /**
     * Get wysiwygOptions
     *
     * @return string
     */
    public function getWysiwygOptions()
    {
        return $this->wysiwygOptions;
    }

    /**
     * Set layoutBoxed
     *
     * @param boolean $layoutBoxed
     *
     * @return Userinterface
     */
    public function setLayoutBoxed($layoutBoxed)
    {
        $this->layoutBoxed = $layoutBoxed;

        return $this;
    }

    /**
     * Get layoutBoxed
     *
     * @return boolean
     */
    public function getLayoutBoxed()
    {
        return $this->layoutBoxed;
    }

    /**
     * Set sidebarMini
     *
     * @param boolean $sidebarMini
     *
     * @return Userinterface
     */
    public function setSidebarMini($sidebarMini)
    {
        $this->sidebarMini = $sidebarMini;

        return $this;
    }

    /**
     * Get sidebarMini
     *
     * @return boolean
     */
    public function getSidebarMini()
    {
        return $this->sidebarMini;
    }

    /**
     * Set sidebarCollapse
     *
     * @param boolean $sidebarCollapse
     *
     * @return Userinterface
     */
    public function setSidebarCollapse($sidebarCollapse)
    {
        $this->sidebarCollapse = $sidebarCollapse;

        return $this;
    }

    /**
     * Get sidebarCollapse
     *
     * @return boolean
     */
    public function getSidebarCollapse()
    {
        return $this->sidebarCollapse;
    }

    /**
     * Add authToken
     *
     * @param \EMS\CoreBundle\Entity\AuthToken $authToken
     *
     * @return Userinterface
     */
    public function addAuthToken(\EMS\CoreBundle\Entity\AuthToken $authToken)
    {
        $this->authTokens[] = $authToken;

        return $this;
    }

    /**
     * Remove authToken
     *
     * @param \EMS\CoreBundle\Entity\AuthToken $authToken
     */
    public function removeAuthToken(\EMS\CoreBundle\Entity\AuthToken $authToken)
    {
        $this->authTokens->removeElement($authToken);
    }

    /**
     * Get authTokens
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAuthTokens()
    {
        return $this->authTokens;
    }
    
    
    /**
     * Set emailNotification
     *
     * @param boolean $emailNotification
     *
     * @return User
     */
    public function setEmailNotification($emailNotification)
    {
        $this->emailNotification = $emailNotification;
        
        return $this;
    }
    
    /**
     * Get emailNotification
     *
     * @return boolean
     */
    public function getEmailNotification()
    {
        return $this->emailNotification;
    }
    
    /**
     * Is enabled
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->enabled;
    }
}
