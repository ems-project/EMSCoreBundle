<?php

namespace EMS\CoreBundle\Entity;

use EMS\CoreBundle\Security\CoreLdapUser;
use Symfony\Component\Security\Core\User\UserInterface;

interface Userinterface
{
    public static function fromLdap(UserInterface $ldapUser, string $emailField): CoreLdapUser;

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
     * @return Userinterface
     */
    public function setCircles($circles);

    /**
     * Set displayName
     *
     * @param string $displayName
     *
     * @return Userinterface
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
     * @return Userinterface
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
     * @return Userinterface
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
     * @return Userinterface
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
     * @return Userinterface
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
     * @return Userinterface
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
     * @return Userinterface
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
     * @return Userinterface
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
     * @return Userinterface
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
