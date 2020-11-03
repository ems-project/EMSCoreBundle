<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

class I18nFilter
{
    /** @var string */
    private $identifier;

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
