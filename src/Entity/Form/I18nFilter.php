<?php

namespace EMS\CoreBundle\Entity\Form;

class I18nFilter
{
    private ?string $identifier = null;

    public function setIdentifier(string $identifier): I18nFilter
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }
}
