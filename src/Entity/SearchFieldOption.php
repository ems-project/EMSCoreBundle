<?php

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\Helpers\Standard\DateTime;

class SearchFieldOption
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    private string $name;
    private string $field;
    private int $orderKey = 0;
    private string $icon;
    /** @var string[] */
    public array $contentTypes = [];
    /** @var string[] */
    public array $operators;

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
     * @return SearchFieldOption
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
     * @return SearchFieldOption
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
     * @return SearchFieldOption
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
     * Set icon.
     *
     * @param string $icon
     *
     * @return SearchFieldOption
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get icon.
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @return string[]
     */
    public function getContentTypes(): array
    {
        return $this->contentTypes;
    }

    /**
     * @param ?string[] $contentTypes
     */
    public function setContentTypes(?array $contentTypes): self
    {
        $this->contentTypes = $contentTypes ?? [];

        return $this;
    }

    /**
     * @return string[]
     */
    public function getOperators(): array
    {
        return $this->operators;
    }

    /**
     * @param ?string[] $operators
     */
    public function setOperators(?array $operators): self
    {
        $this->operators = $operators ?? [];

        return $this;
    }
}
