<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Form\Field\FilterOptionsType;

/**
 * Analyzer.
 *
 * @ORM\Table(name="filter")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\FilterRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Filter
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    private $name;

    /**
     * @var bool
     *
     * @ORM\Column(name="dirty", type="boolean")
     */
    private $dirty;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    private $label;

    /**
     * @var array
     *
     * @ORM\Column(name="options", type="json_array")
     */
    private $options;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var int
     *
     * @ORM\Column(name="order_key", type="integer", nullable=true)
     */
    private $orderKey;

    public function __construct()
    {
        $this->options = [];
        $this->dirty = true;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Filter
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
     * Set options.
     *
     * @param array $options
     *
     * @return Filter
     */
    public function setOptions($options)
    {
        $this->options = $options;

        foreach ($this->options as $key => $data) {
            if ('type' != $key and !in_array($key, FilterOptionsType::FIELDS_BY_TYPE[$this->options['type']])) {
                unset($this->options[$key]);
            } elseif (null === $this->options[$key]) {
                unset($this->options[$key]);
            }
        }

        return $this;
    }

    /**
     * Get options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return Filter
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified.
     *
     * @param \DateTime $modified
     *
     * @return Filter
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified.
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
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
}
