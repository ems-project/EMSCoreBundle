<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\Helpers\Standard\DateTime;

/**
 * DataField.
 *
 * @ORM\Table(name="search_field_option")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SearchFieldOptionRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class SearchFieldOption
{
    use CreatedModifiedTrait;
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
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="field", type="text", length=255)
     */
    private $field;

    /**
     * @ORM\Column(name="orderKey", type="integer")
     */
    private int $orderKey = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="icon", type="text", length=255, nullable=true)
     */
    private $icon;

    /**
     * @var string[]
     *
     * @ORM\Column(name="contentTypes", type="json_array")
     */
    public array $contentTypes = [];

    /**
     * @var string[]
     *
     * @ORM\Column(name="operators", type="json_array")
     */
    public array $operators;

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
