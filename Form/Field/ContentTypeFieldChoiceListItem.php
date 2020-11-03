<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

class ContentTypeFieldChoiceListItem
{
    private $label;
    private $value;

    public function __construct($value, $label)
    {
        $this->value = $value;
        $this->label = $label;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function __toString()
    {
        return $this->getValue();
    }
}
