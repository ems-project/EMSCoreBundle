<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;

class Sequence
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    private int $value = 1;
    private int $version = 0;

    public function __construct(private string $name)
    {
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    public function updateVersion(): void
    {
        ++$this->version;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Sequence
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set value.
     *
     * @param int $value
     *
     * @return Sequence
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value.
     *
     * @return int
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * inc.
     *
     * @return int
     */
    public function inc()
    {
        $this->value = $this->value + 1;

        return $this->value;
    }

    /**
     * Get version.
     *
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }
}
