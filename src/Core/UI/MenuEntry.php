<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

class MenuEntry
{
    private string $label;
    private string $icon;
    private string $url;
    private ?string $color;
    private ?string $badge = null;

    public function __construct(string $label, string $icon, string $url, ?string $color = null)
    {
        $this->label = $label;
        $this->icon = $icon;
        $this->url = $url;
        $this->color = $color;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function hasBadge(): bool
    {
        return null !== $this->badge;
    }

    public function setBadge(?string $badge): void
    {
        $this->badge = $badge;
    }
}
