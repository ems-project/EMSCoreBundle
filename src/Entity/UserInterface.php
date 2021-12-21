<?php

namespace EMS\CoreBundle\Entity;

interface UserInterface extends \Symfony\Component\Security\Core\User\UserInterface
{
    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated();

    /**
     * Get modified.
     *
     * @return \DateTime
     */
    public function getModified();

    /**
     * Get circles.
     *
     * @return array
     */
    public function getCircles();

    /**
     * Set circles.
     *
     * @param array $circles
     *
     * @return UserInterface
     */
    public function setCircles($circles);

    /**
     * Set displayName.
     *
     * @param string $displayName
     *
     * @return UserInterface
     */
    public function setDisplayName($displayName);

    /**
     * Get displayName.
     *
     * @return string
     */
    public function getDisplayName();

    /**
     * Set allowedToConfigureWysiwyg.
     *
     * @param bool $allowedToConfigureWysiwyg
     *
     * @return UserInterface
     */
    public function setAllowedToConfigureWysiwyg($allowedToConfigureWysiwyg);

    /**
     * Get allowedToConfigureWysiwyg.
     *
     * @return bool
     */
    public function getAllowedToConfigureWysiwyg();

    /**
     * Set wysiwygProfile.
     *
     * @return UserInterface
     */
    public function setWysiwygProfile(WysiwygProfile $wysiwygProfile);

    public function getWysiwygProfile(): ?WysiwygProfile;

    /**
     * Set wysiwygOptions.
     *
     * @param string $wysiwygOptions
     *
     * @return UserInterface
     */
    public function setWysiwygOptions($wysiwygOptions);

    /**
     * Get wysiwygOptions.
     *
     * @return string
     */
    public function getWysiwygOptions();

    /**
     * Set layoutBoxed.
     *
     * @param bool $layoutBoxed
     *
     * @return UserInterface
     */
    public function setLayoutBoxed($layoutBoxed);

    /**
     * Get layoutBoxed.
     *
     * @return bool
     */
    public function getLayoutBoxed();

    /**
     * Set sidebarMini.
     *
     * @param bool $sidebarMini
     *
     * @return UserInterface
     */
    public function setSidebarMini($sidebarMini);

    /**
     * Get sidebarMini.
     *
     * @return bool
     */
    public function getSidebarMini();

    /**
     * Set emailNotification.
     *
     * @param bool $emailNotification
     *
     * @return UserInterface
     */
    public function setEmailNotification($emailNotification);

    /**
     * Get emailNotification.
     *
     * @return bool
     */
    public function getEmailNotification();

    /**
     * Set sidebarCollapse.
     *
     * @param bool $sidebarCollapse
     *
     * @return UserInterface
     */
    public function setSidebarCollapse($sidebarCollapse);

    /**
     * Get sidebarCollapse.
     *
     * @return bool
     */
    public function getSidebarCollapse();

    /**
     * Add authToken.
     *
     * @return UserInterface
     */
    public function addAuthToken(AuthToken $authToken);

    /**
     * Remove authToken.
     */
    public function removeAuthToken(AuthToken $authToken);

    /**
     * Get authTokens.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAuthTokens();

    /**
     * Is enabled.
     *
     * @return bool
     */
    public function isEnabled();

    /**
     * @return string
     */
    public function getUsername();

    /**
     * @return string
     */
    public function getEmail();

    /**
     * @return \DateTime|null
     */
    public function getLastLogin();

    /**
     * @return array{id: int|string, username:string, displayName:string, roles:array<string>, email:string, circles:array<string>, lastLogin: ?string}
     */
    public function toArray(): array;
}
