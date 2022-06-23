<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\Collection;

interface UserInterface extends \Symfony\Component\Security\Core\User\UserInterface
{
    public function getCreated(): \DateTime;

    public function getModified(): \DateTime;

    /** @return string[] */
    public function getCircles(): array;

    /** @param string[] $circles */
    public function setCircles(array $circles): self;

    public function setDisplayName(string $displayName): self;

    public function getDisplayName(): string;

    public function setAllowedToConfigureWysiwyg(bool $allowedToConfigureWysiwyg): self;

    public function getAllowedToConfigureWysiwyg(): ?bool;

    public function setWysiwygProfile(?WysiwygProfile $wysiwygProfile): self;

    public function getWysiwygProfile(): ?WysiwygProfile;

    public function setWysiwygOptions(?string $wysiwygOptions): self;

    public function getWysiwygOptions(): ?string;

    public function setLayoutBoxed(bool $layoutBoxed): self;

    public function getLayoutBoxed(): bool;

    public function setSidebarMini(bool $sidebarMini): self;

    public function getSidebarMini(): bool;

    public function setEmailNotification(bool $emailNotification): self;

    public function getEmailNotification(): bool;

    public function setSidebarCollapse(bool $sidebarCollapse): self;

    public function getSidebarCollapse(): bool;

    public function addAuthToken(AuthToken $authToken): self;

    public function removeAuthToken(AuthToken $authToken): void;

    /** @return Collection<int, AuthToken> */
    public function getAuthTokens(): Collection;

    public function isEnabled(): bool;

    public function getUsername(): string;

    public function getEmail(): string;

    public function getLastLogin(): ?\DateTime;

    /**
     * @return array{id: int|string, username:string, displayName:string, roles:array<string>, email:string, circles:array<string>, lastLogin: ?string}
     */
    public function toArray(): array;
}
