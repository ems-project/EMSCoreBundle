<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DataField
 *
 * @ORM\Table(name="view")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\LinkRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class View
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
     * @ORM\Column(name="type", type="string", length=255)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="icon", type="string", length=255, nullable=true)
     */
    private $icon; 
    
    /**
     * @var string
     *
     * @ORM\Column(name="options", type="json_array", nullable=true)
     */
    private $options;
    
    /**
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    private $orderKey;

    /**
     * @ORM\ManyToOne(targetEntity="ContentType", inversedBy="views")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private $contentType;

    /**
     * @var bool
     *
     * @ORM\Column(name="public", type="boolean", options={"default" : 0})
     */
    private $public;


    public function __construct()
    {
        $this->public = false;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
    	$this->modified = new \DateTime();
        	if(!isset($this->created)){
    		$this->created = $this->modified;
    	}
    	if(!isset($this->orderKey)){
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
     * @return View
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
     * @return View
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
     * @return View
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
     * Set type
     *
     * @param string $type
     *
     * @return View
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set icon
     *
     * @param string $icon
     *
     * @return View
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get icon
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * Set options
     *
     * @param string $options
     *
     * @return View
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get options
     *
     * @return string
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set orderKey
     *
     * @param integer $orderKey
     *
     * @return View
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
     * Set contentType
     *
     * @param \EMS\CoreBundle\Entity\ContentType $contentType
     *
     * @return View
     */
    public function setContentType(\EMS\CoreBundle\Entity\ContentType $contentType = null)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Get contentType
     *
     * @return \EMS\CoreBundle\Entity\ContentType
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * @param bool $public
     * @return View
     */
    public function setPublic(bool $public): View
    {
        $this->public = $public;
        return $this;
    }


}
