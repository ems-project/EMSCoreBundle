<?php

namespace EMS\CoreBundle\Entity;

use EMS\CoreBundle\Security\CoreLdapUser;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

interface UserInterface
{
    public static function fromLdap(SymfonyUserInterface $ldapUser, string $emailField): CoreLdapUser;

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated();
    
    /**
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified();

    /**
     * Get circles
     *
     * @return array
     */
    public function getCircles();
    
    
    /**
     * Set circles
     *
     * @param array $circles
     *
     * @return UserInterface
     */
    public function setCircles($circles);

    /**
     * Set displayName
     *
     * @param string $displayName
     *
     * @return UserInterface
     */
    public function setDisplayName($displayName);

    /**
     * Get displayName
     *
     * @return string
     */
    public function getDisplayName();

    /**
     * Set allowedToConfigureWysiwyg
     *
     * @param boolean $allowedToConfigureWysiwyg
     *
     * @return UserInterface
     */
    public function setAllowedToConfigureWysiwyg($allowedToConfigureWysiwyg);

    /**
     * Get allowedToConfigureWysiwyg
     *
     * @return boolean
     */
    public function getAllowedToConfigureWysiwyg();

    /**
     * Set wysiwygProfile
     *
     * @param WysiwygProfile $wysiwygProfile
     *
     * @return UserInterface
     */
    public function setWysiwygProfile(WysiwygProfile $wysiwygProfile = null);

    /**
     * Get wysiwygProfile
     *
     * @return WysiwygProfile
     */
    public function getWysiwygProfile();

    /**
     * Set wysiwygOptions
     *
     * @param string $wysiwygOptions
     *
     * @return UserInterface
     */
    public function setWysiwygOptions($wysiwygOptions);

    /**
     * Get wysiwygOptions
     *
     * @return string
     */
    public function getWysiwygOptions();

    /**
     * Set layoutBoxed
     *
     * @param boolean $layoutBoxed
     *
     * @return UserInterface
     */
    public function setLayoutBoxed($layoutBoxed);

    /**
     * Get layoutBoxed
     *
     * @return boolean
     */
    public function getLayoutBoxed();
    
    /**
     * Set sidebarMini
     *
     * @param boolean $sidebarMini
     *
     * @return UserInterface
     */
    public function setSidebarMini($sidebarMini);
    
    /**
     * Get sidebarMini
     *
     * @return boolean
     */
    public function getSidebarMini();
    
    
    /**
     * Set emailNotification
     *
     * @param boolean $emailNotification
     *
     * @return UserInterface
     */
    public function setEmailNotification($emailNotification);
    
    /**
     * Get emailNotification
     *
     * @return boolean
     */
    public function getEmailNotification();
    
    /**
     * Set sidebarCollapse
     *
     * @param boolean $sidebarCollapse
     *
     * @return UserInterface
     */
    public function setSidebarCollapse($sidebarCollapse);

    /**
     * Get sidebarCollapse
     *
     * @return boolean
     */
    public function getSidebarCollapse();

    /**
     * Add authToken
     *
     * @param \EMS\CoreBundle\Entity\AuthToken $authToken
     *
     * @return UserInterface
     */
    public function addAuthToken(\EMS\CoreBundle\Entity\AuthToken $authToken);

    /**
     * Remove authToken
     *
     * @param \EMS\CoreBundle\Entity\AuthToken $authToken
     */
    public function removeAuthToken(\EMS\CoreBundle\Entity\AuthToken $authToken);
    
    /**
     * Get authTokens
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAuthTokens();
    
    /**
     * Is enabled
     *
     * @return boolean
     */
    public function isEnabled();


    /**
     * @return string
     */
    public function getUsername();
}
