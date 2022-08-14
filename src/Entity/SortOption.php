<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\Helpers\Standard\DateTime;

/**
 * @ORM\Table(name="sort_option")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SortOptionRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class SortOption
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
     * @var bool
     *
     * @ORM\Column(name="inverted", type="boolean")
     */
    private $inverted;

    /**
     * @var string
     *
     * @ORM\Column(name="icon", type="text", length=255, nullable=true)
     */
    private $icon;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    /******************************************************************
     *
     * Generated functions
     *
     *******************************************************************/

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

    /**
     * Get icon.
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }
}
