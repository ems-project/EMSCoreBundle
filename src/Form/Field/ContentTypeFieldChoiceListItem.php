<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

class ContentTypeFieldChoiceListItem implements \Stringable
{
    public function __construct(private readonly ?string $value, private readonly string $label)
    {
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->getValue() ?? $this->getLabel();
    }
}
