<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

class MenuEntry
{
    private string $label;
    private string $icon;

    public function __construct(string $label, string $icon)
    {
        $this->label = $label;
        $this->icon = $icon;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }
}
