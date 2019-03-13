<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DataField
 *
 * @ORM\Table(name="search_field_option")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SearchFieldOptionRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class SearchFieldOption
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
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    private $orderKey;
    
    /**
     * @var string
     *
     * @ORM\Column(name="icon", type="text", length=255, nullable=true)
     */
    private $icon;

    /**
     * @var array
     *
     * @ORM\Column(name="contentTypes", type="json_array")
     */
    public $contentTypes;

    /**
     * @var array
     *
     * @ORM\Column(name="operators", type="json_array")
     */
    public $operators;

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
        if (!isset($this->orderKey)) {
            $this->orderKey = 0;
        }
    }
    
    /******************************************************************
     *
     * Generated functions
     *
     *******************************************************************/
    
    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Set created
     *
     * @param \DateTime $created
     *
     * @return SortOption
     */
    public function setCreated($created)
    {
        $this->created = $created;
        
        return $this;
    }
    
    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }
    
    /**
     * Set modified
     *
     * @param \DateTime $modified
     *
     * @return SortOption
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
        
        return $this;
    }
    
    /**
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }
    
    /**
     * Set name
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
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Set field
     *
     * @param string $field
     *
     * @return SortOption
     */
    public function setField($field)
    {
        $this->field= $field;
        
        return $this;
    }
    
    /**
     * Get field
     *
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }
    
    /**
     * Set orderKey
     *
     * @param integer $orderKey
     *
     * @return SortOption
     */
    public function setOrderKey($orderKey)
    {
        $this->orderKey = $orderKey;
        
        return $this;
    }
    
    /**
     * Get orderKey
     *
     * @return integer
     */
    public function getOrderKey()
    {
        return $this->orderKey;
    }
    
    /**
     * Set icon
     *
     * @param boolean $icon
     *
     * @return SortOption
     */
    public function setIcon($icon)
    {
        $this->icon= $icon;
        
        return $this;
    }
    
    /**
     * Get icon
     *
     * @return boolean
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @return array|null
     */
    public function getContentTypes()
    {
        return $this->contentTypes;
    }

    /**
     * @param array|null $contentTypes
     * @return SearchFieldOption
     */
    public function setContentTypes($contentTypes)
    {
        $this->contentTypes = $contentTypes;
        return $this;
    }

    /**
     * @return array
     */
    public function getOperators()
    {
        return $this->operators;
    }

    /**
     * @param array|null $operators
     * @return SearchFieldOption
     */
    public function setOperators($operators)
    {
        $this->operators = $operators;
        return $this;
    }
}
