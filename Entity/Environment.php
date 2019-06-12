<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;

/**
 * Environment
 *
 * @ORM\Table(name="environment")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\EnvironmentRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Environment extends JsonDeserializer implements \JsonSerializable
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
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="alias", type="string", length=255)
     */
    protected $alias;
    
    /**
     * @var array
     */
    protected $indexes;
    
    /**
     * @var int
     */
    protected $total;
    
    /**
     * @var integer
     */
    protected $counter;
    
    /**
     * @var integer
     */
    protected $deletedRevision;
    
    /**
     * @var string
     *
     * @ORM\Column(name="color", type="string", length=50, nullable=true)
     */
    protected $color;
    
    /**
     * @var string
     *
     * @ORM\Column(name="baseUrl", type="string", length=1024, nullable=true)
     */
    protected $baseUrl;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="managed", type="boolean")
     */
    protected $managed;

    /**
     * @ORM\ManyToMany(targetEntity="Revision", mappedBy="environments")
     */
    protected $revisions;

    /**
     * @var array
     *
     * @ORM\Column(name="circles", type="json_array", nullable=true)
     */
    protected $circles;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="in_default_search", type="boolean", nullable=true)
     */
    protected $inDefaultSearch;

    /**
     * @var string
     *
     * @ORM\Column(name="extra", type="text", nullable=true)
     */
    protected $extra;
    

    /**
     * @ORM\OneToMany(targetEntity="ContentType", mappedBy="environment", cascade={"remove"})
     */
    protected $contentTypesHavingThisAsDefault;
    
    /**
     * @var int
     *
     * @ORM\Column(name="order_key", type="integer", nullable=true)
     */
    protected $orderKey;

    /**
     * @ORM\OneToMany(targetEntity="SingleTypeIndex", mappedBy="environment", cascade={"persist", "remove"})
     * @ORM\OrderBy({"name" = "ASC"})
     */
    protected $singleTypeIndexes;
    
    
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
     * Constructor
     */
    public function __construct()
    {
        $this->revisions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->singleTypeIndexes = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * ToString
     */
    public function __toString()
    {
        return $this->name;
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
     * @return Environment
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
     * @return Environment
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
     * @return Environment
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
     * Set index
     *
     * @param array $indexes
     *
     * @return Environment
     */
    public function setIndexes(array $indexes)
    {
        $this->indexes = $indexes;

        return $this;
    }

    /**
     * Get indexes
     *
     * @return array
     */
    public function getIndexes()
    {
        return $this->indexes;
    }
    /**
     * Set total
     *
     * @param int $total
     *
     * @return Environment
     */
    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }

    /**
     * Get total
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Get counter
     *
     * @return integer
     */
    public function getCounter()
    {
        return $this->counter;
    }
    /**
     * Set counter
     *
     * @param integer $counter
     *
     * @return Environment
     */
    public function setCounter($counter)
    {
        $this->counter = $counter;

        return $this;
    }

    /**
     * Get counter of deleted revision
     *
     * @return integer
     */
    public function getDeletedRevision()
    {
        return $this->deletedRevision;
    }
    /**
     * Set counter of deleted revision
     *
     * @param integer $deletedRevision
     *
     * @return Environment
     */
    public function setDeletedRevision($deletedRevision)
    {
        $this->deletedRevision = $deletedRevision;

        return $this;
    }
    

    /**
     * Add revision
     *
     * @param \EMS\CoreBundle\Entity\Revision $revision
     *
     * @return Environment
     */
    public function addRevision(\EMS\CoreBundle\Entity\Revision $revision)
    {
        $this->revisions[] = $revision;

        return $this;
    }

    /**
     * Remove revision
     *
     * @param \EMS\CoreBundle\Entity\Revision $revision
     */
    public function removeRevision(\EMS\CoreBundle\Entity\Revision $revision)
    {
        $this->revisions->removeElement($revision);
    }

    /**
     * Get revisions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRevisions()
    {
        return $this->revisions;
    }

    /**
     * Set managed
     *
     * @param boolean $managed
     *
     * @return Environment
     */
    public function setManaged($managed)
    {
        $this->managed = $managed;

        return $this;
    }

    /**
     * Get managed
     *
     * @return bool
     */
    public function getManaged()
    {
        return $this->managed;
    }

    /**
     * Set color
     *
     * @param string $color
     *
     * @return Environment
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set alias
     *
     * @param string $alias
     *
     * @return Environment
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Get alias
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Set baseUrl
     *
     * @param string $baseUrl
     *
     * @return Environment
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * Get baseUrl
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Set circles
     *
     * @param array $circles
     *
     * @return Environment
     */
    public function setCircles($circles)
    {
        $this->circles = $circles;
    
        return $this;
    }
    
    /**
     * Get circles
     *
     * @return array
     */
    public function getCircles()
    {
        return $this->circles;
    }

    /**
     * Set inDefaultSearch
     *
     * @param boolean $inDefaultSearch
     *
     * @return Environment
     */
    public function setInDefaultSearch($inDefaultSearch)
    {
        $this->inDefaultSearch = $inDefaultSearch;

        return $this;
    }

    /**
     * Get inDefaultSearch
     *
     * @return boolean
     */
    public function getInDefaultSearch()
    {
        return $this->inDefaultSearch;
    }

    /**
     * Set extra
     *
     * @param string $extra
     *
     * @return Environment
     */
    public function setExtra($extra)
    {
        $this->extra = $extra;

        return $this;
    }

    /**
     * Get extra
     *
     * @return string
     */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * Add contentTypesHavingThisAsDefault
     *
     * @param \EMS\CoreBundle\Entity\ContentType $contentTypesHavingThisAsDefault
     *
     * @return Environment
     */
    public function addContentTypesHavingThisAsDefault(\EMS\CoreBundle\Entity\ContentType $contentTypesHavingThisAsDefault)
    {
        $this->contentTypesHavingThisAsDefault[] = $contentTypesHavingThisAsDefault;

        return $this;
    }

    /**
     * Remove contentTypesHavingThisAsDefault
     *
     * @param \EMS\CoreBundle\Entity\ContentType $contentTypesHavingThisAsDefault
     */
    public function removeContentTypesHavingThisAsDefault(\EMS\CoreBundle\Entity\ContentType $contentTypesHavingThisAsDefault)
    {
        $this->contentTypesHavingThisAsDefault->removeElement($contentTypesHavingThisAsDefault);
    }

    /**
     * Get contentTypesHavingThisAsDefault
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getContentTypesHavingThisAsDefault()
    {
        return $this->contentTypesHavingThisAsDefault;
    }
    
    /**
     * Set orderKey
     *
     * @param integer $orderKey
     *
     * @return Environment
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
     * Add single type index
     *
     * @param SingleTypeIndex $index
     *
     * @return Environment
     */
    public function addSingleTypeIndex(SingleTypeIndex $index)
    {
        $this->singleTypeIndexes[] = $index;

        return $this;
    }

    /**
     * Remove single type index
     *
     * @param SingleTypeIndex $index
     */
    public function removeSingleTypeIndex(SingleTypeIndex $index)
    {
        $this->singleTypeIndexes->removeElement($index);
    }

    /**
     * Get single type indexes
     *
     * @return Collection
     */
    public function getSingleTypeIndexes()
    {
        return $this->singleTypeIndexes;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $json = new JsonClass(get_object_vars($this), __CLASS__);
        $json->removeProperty('id');

        return $json;
    }
}
