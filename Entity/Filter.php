<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Analyzer
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
	 * @ORM\Column(name="label", type="string", length=255, unique=true)
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
    
    public function __construct() {
    	$this->options = [];
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
    }


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Set name
     *
     * @param string $name
     *
     * @return Analyzer
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
     * Set options
     *
     * @param array $options
     *
     * @return Analyzer
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set created
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
     * @return Analyzer
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
     * Set dirty
     *
     * @param boolean $dirty
     *
     * @return ContentType
     */
    public function setDirty($dirty)
    {
    	$this->dirty = $dirty;
    	
    	return $this;
    }
    
    /**
     * Get dirty
     *
     * @return boolean
     */
    public function getDirty()
    {
    	return $this->dirty;
    }
    
    /**
     * Set label
     *
     * @param string $label
     *
     * @return ContentType
     */
    public function setLabel($label)
    {
    	$this->label = $label;
    	
    	return $label;
    }
    
    /**
     * Get label
     *
     * @return string
     */
    public function getLabel()
    {
    	return $this->$label;
    }
}

