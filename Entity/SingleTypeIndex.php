<?php
namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Form\DataField\ContainerFieldType;

/**
 * ContentType
 *
 * @ORM\Table(name="single_type_index")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SingleTypeIndexRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class SingleTypeIndex
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint")
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
     * @var ContentType
     *
     * @ORM\ManyToOne(targetEntity="ContentType", inversedBy="singleTypeIndexes")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private $contentType;

    /**
     * @ORM\ManyToOne(targetEntity="Environment", inversedBy="singleTypeIndexes")
     * @ORM\JoinColumn(name="environment_id", referencedColumnName="id")
     */
    private $environment;

    public function __toString()
    {
    	return $this->name;
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
     * Set created
     *
     * @param \DateTime $created
     *
     * @return SingleTypeIndex
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
     * @return SingleTypeIndex
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
     * @return SingleTypeIndex
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
     * Set environment
     *
     * @param Environment $environment
     *
     * @return SingleTypeIndex
     */
    public function setEnvironment(Environment $environment = null)
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Get environment
     *
     * @return Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }


    /**
     * Set content type
     *
     * @param ContentType $contentType
     *
     * @return SingleTypeIndex
     */
    public function setContentType(ContentType $contentType = null)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Get content type
     *
     * @return ContentType
     */
    public function getContentType()
    {
        return $this->contentType;
    }

}
