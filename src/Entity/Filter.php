<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\Field\FilterOptionsType;
use EMS\Helpers\Standard\DateTime;

/**
 * Analyzer.
 *
 * @ORM\Table(name="filter")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Filter extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected string $name = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="dirty", type="boolean")
     */
    protected $dirty = true;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected $label;

    /**
     * @var array<mixed>
     *
     * @ORM\Column(name="options", type="json")
     */
    protected array $options = [];

    /**
     * @var int
     *
     * @ORM\Column(name="order_key", type="integer", nullable=true)
     */
    protected $orderKey;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
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

    public function setName(string $name): Filter
    {
        $this->name = $name;

        return $this;
    }

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
