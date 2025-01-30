<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\Field\AnalyzerOptionsType;

class Analyzer extends JsonDeserializer implements \JsonSerializable, EntityInterface
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
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    public function setName(string $name): Analyzer
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
     * Set options.
     *
     * @param array<mixed> $options
     *
     * @return Analyzer
     */
    public function setOptions(array $options)
    {
        $this->options = $options;

        foreach ($this->options as $key => $data) {
            if ('type' != $key and !\in_array($key, AnalyzerOptionsType::FIELDS_BY_TYPE[$this->options['type']])) {
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
    public function getOptions(?string $esVersion = null): array
    {
        $options = $this->options;

        if (null === $esVersion) {
            return $options;
        }

        if (isset($options['filter'])) {
            $options['filter'] = \array_values(\array_filter($options['filter'], fn (string $f) => 'standard' !== $f));
        }

        return \array_filter($options);
    }

    /**
     * Set dirty.
     *
     * @param bool $dirty
     *
     * @return Analyzer
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
     * @return Analyzer
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
     * @return Analyzer
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

    public static function fromJson(string $json, ?\EMS\CommonBundle\Entity\EntityInterface $filter = null): Analyzer
    {
        $meta = JsonClass::fromJsonString($json);
        $filter = $meta->jsonDeserialize($filter);
        if (!$filter instanceof Analyzer) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $filter;
    }
}
