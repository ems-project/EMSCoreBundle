<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\Field\FilterOptionsType;
use EMS\Helpers\Standard\DateTime;

class Filter extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    protected string $name = '';
    /** @var bool */
    protected $dirty = true;
    /** @var string */
    protected $label;
    /** @var array<mixed> */
    protected array $options = [];
    /** @var int */
    protected $orderKey = 0;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function setName(string $name): Filter
    {
        $this->name = $name;

        return $this;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param array<mixed> $options
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        foreach ($this->options as $key => $data) {
            if ('type' != $key and !\in_array($key, FilterOptionsType::FIELDS_BY_TYPE[$this->options['type']])) {
                unset($this->options[$key]);
            } elseif (null === $this->options[$key]) {
                unset($this->options[$key]);
            }
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set dirty.
     *
     * @param bool $dirty
     *
     * @return Filter
     */
    public function setDirty($dirty)
    {
        $this->dirty = $dirty;

        return $this;
    }

    /**
     * Get dirty.
     *
     * @return bool
     */
    public function getDirty()
    {
        return $this->dirty;
    }

    /**
     * Set label.
     *
     * @param string $label
     *
     * @return Filter
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return Filter
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

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');

        return $json;
    }

    public static function fromJson(string $json, ?\EMS\CommonBundle\Entity\EntityInterface $filter = null): Filter
    {
        $meta = JsonClass::fromJsonString($json);
        $filter = $meta->jsonDeserialize($filter);
        if (!$filter instanceof Filter) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $filter;
    }
}
