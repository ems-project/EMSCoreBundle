<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

class MenuEntry
{
    private string $label;
    private string $icon;
    private string $url;

    public function __construct(string $label, string $icon, string $url)
    {
        $this->label = $label;
        $this->icon = $icon;
        $this->url = $url;
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
}
