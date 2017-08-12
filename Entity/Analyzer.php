<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Analyzer
 *
 * @ORM\Table(name="analyzer")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\AnalyzerRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Analyzer
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
     * @ORM\Column(name="mofified", type="datetime")
     */
    private $mofified;
    
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
     * Set mofified
     *
     * @param \DateTime $mofified
     *
     * @return Analyzer
     */
    public function setMofified($mofified)
    {
        $this->mofified = $mofified;

        return $this;
    }

    /**
     * Get mofified
     *
     * @return \DateTime
     */
    public function getMofified()
    {
        return $this->mofified;
    }
}

