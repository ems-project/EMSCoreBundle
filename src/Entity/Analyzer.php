<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\Field\AnalyzerOptionsType;

/**
 * Analyzer.
 *
 * @ORM\Table(name="analyzer")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Analyzer extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected string $name = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="dirty", type="boolean")
     */
    protected $dirty;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected $label;

    /**
     * @var array
     *
     * @ORM\Column(name="options", type="json_array")
     */
    protected $options;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    protected $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    protected $modified;

    /**
     * @var int
     *
     * @ORM\Column(name="order_key", type="integer", nullable=true)
     */
    protected $orderKey;

    public function __construct()
    {
        $this->options = [];
        $this->dirty = true;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified(): void
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

    public function setName(string $name): Analyzer
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set options.
     *
     * @param array $options
     *
     * @return Analyzer
     */
    public function setOptions($options)
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

    public function getOptions(?string $esVersion = null): array
    {
        $options = $this->options ?? [];

        if (null === $esVersion) {
            return $options;
        }

        if (isset($options['filter']) && \version_compare($esVersion, '7.0') >= 0) {
            $options['filter'] = \array_values(\array_filter($options['filter'], function (string $f) {
                return 'standard' !== $f;
            }));
        }

        return \array_filter($options);
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return Analyzer
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
     * @return Analyzer
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

    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), __CLASS__);
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
