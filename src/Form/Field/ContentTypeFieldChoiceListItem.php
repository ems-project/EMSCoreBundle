<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

class ContentTypeFieldChoiceListItem
{
    private string $label;
    private ?string $value;

    public function __construct(?string $value, string $label)
    {
        $this->value = $value;
        $this->label = $label;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function __toString(): string
    {
        return $this->getValue() ?? $this->getLabel();
    }
}
