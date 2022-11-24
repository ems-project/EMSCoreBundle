<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

final class TableAction
{
    private string $name;
    private string $labelKey;
    private string $confirmationKey;
    private string $icon;
    private string $cssClass = 'btn btn-default';

    public function __construct(string $name, string $icon, string $labelKey, string $confirmationKey)
    {
        $this->name = $name;
        $this->icon = $icon;
        $this->labelKey = $labelKey;
        $this->confirmationKey = $confirmationKey;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabelKey(): string
    {
        return $this->labelKey;
    }

    public function getConfirmationKey(): string
    {
        return $this->confirmationKey;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getCssClass(): string
    {
        return $this->cssClass;
    }

    public function setCssClass(string $cssClass): void
    {
        $this->cssClass = $cssClass;
    }
}
