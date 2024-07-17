<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use Symfony\Component\Translation\TranslatableMessage;

final class TableAction
{
    private string $cssClass = 'btn btn-default';

    public function __construct(
        private readonly string $name,
        private readonly string $icon,
        private readonly TranslatableMessage $labelKey,
        private readonly ?TranslatableMessage $confirmationKey = null)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabelKey(): TranslatableMessage
    {
        return $this->labelKey;
    }

    public function getConfirmationKey(): ?TranslatableMessage
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
