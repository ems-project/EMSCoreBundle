<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\Collection;

interface UserInterface extends \Symfony\Component\Security\Core\User\UserInterface
{
    public function getCreated(): \DateTimeInterface;

    public function getModified(): \DateTimeInterface;

    /** @return string[] */
    public function getCircles(): array;

    /** @param string[] $circles */
    public function setCircles(array $circles): self;

    public function setDisplayName(string $displayName): self;

    public function getDisplayName(): string;

    public function setWysiwygProfile(?WysiwygProfile $wysiwygProfile): self;

    public function getWysiwygProfile(): ?WysiwygProfile;

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

    public function getExpirationDate(): ?\DateTimeInterface;

    public function hasRole(string $role): bool;

    public function isExpired(): bool;

    /**
     * @return array{
     *     id: int|string,
     *     username:string,
     *     displayName:string,
     *     roles:array<string>,
     *     email:string,
     *     circles:array<string>,
     *     lastLogin: ?string,
     *     expirationDate: ?string,
     *     userOptions: ?array<string, mixed>
     * }
     */
    public function toArray(): array;
}
