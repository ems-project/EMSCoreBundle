<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\Helpers\Standard\DateTime;

class SortOption
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    private string $name;
    private string $field;
    private int $orderKey = 0;
    private bool $inverted;
    private ?string $icon = null;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return SortOption
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
     * Set field.
     *
     * @param string $field
     *
     * @return SortOption
     */
    public function setField($field)
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Get field.
     *
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return SortOption
     */
    public function setOrderKey($orderKey)
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    /**
     * Get orderKey.
     *
     * @return int
     */
    public function getOrderKey()
    {
        return $this->orderKey;
    }

    /**
     * Set inverted.
     *
     * @param bool $inverted
     *
     * @return SortOption
     */
    public function setInverted($inverted)
    {
        $this->inverted = $inverted;

        return $this;
    }

    /**
     * Get inverted.
     *
     * @return bool
     */
    public function getInverted()
    {
        return $this->inverted;
    }

    /**
     * Set icon.
     *
     * @param string $icon
     *
     * @return SortOption
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }
}
