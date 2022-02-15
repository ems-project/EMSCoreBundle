<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;

/**
 * Environment.
 *
 * @ORM\Table(name="environment")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\EnvironmentRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Environment extends JsonDeserializer implements \JsonSerializable, EntityInterface
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
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected string $name = '';

    /**
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     */
    protected ?string $label;

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
     * @var int
     */
    protected $counter;

    /**
     * @var int
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
     * @var bool
     *
     * @ORM\Column(name="snapshot", type="boolean", options={"default": false})
     */
    protected $snapshot = false;

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
     * @ORM\Column(name="update_referrers", type="boolean", nullable=false, options={"default": false}))
     */
    protected bool $updateReferrers = false;

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
     * Constructor.
     */
    public function __construct()
    {
        $this->revisions = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * ToString.
     */
    public function __toString()
    {
        return $this->name;
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
     * Set created.
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
     * @return Environment
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
     * Set name.
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

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set index.
     *
     * @return Environment
     */
    public function setIndexes(array $indexes)
    {
        $this->indexes = $indexes;

        return $this;
    }

    /**
     * Get indexes.
     *
     * @return array
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /**
     * Set total.
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
     * Get total.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Get counter.
     *
     * @return int
     */
    public function getCounter()
    {
        return $this->counter;
    }

    /**
     * Set counter.
     *
     * @param int $counter
     *
     * @return Environment
     */
    public function setCounter($counter)
    {
        $this->counter = $counter;

        return $this;
    }

    /**
     * Get counter of deleted revision.
     *
     * @return int
     */
    public function getDeletedRevision()
    {
        return $this->deletedRevision;
    }

    /**
     * Set counter of deleted revision.
     *
     * @param int $deletedRevision
     *
     * @return Environment
     */
    public function setDeletedRevision($deletedRevision)
    {
        $this->deletedRevision = $deletedRevision;

        return $this;
    }

    /**
     * Add revision.
     *
     * @return Environment
     */
    public function addRevision(Revision $revision)
    {
        $this->revisions[] = $revision;

        return $this;
    }

    /**
     * Remove revision.
     */
    public function removeRevision(Revision $revision)
    {
        $this->revisions->removeElement($revision);
    }

    /**
     * Get revisions.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRevisions()
    {
        return $this->revisions;
    }

    /**
     * Set managed.
     *
     * @param bool $managed
     *
     * @return Environment
     */
    public function setManaged($managed)
    {
        $this->managed = $managed;

        return $this;
    }

    public function getManaged(): bool
    {
        return $this->managed;
    }

    /**
     * Set snapshot.
     *
     * @param bool $snapshot
     *
     * @return Environment
     */
    public function setSnapshot($snapshot)
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    /**
     * Get snapshot.
     *
     * @return bool
     */
    public function getSnapshot()
    {
        return $this->snapshot;
    }

    /**
     * Set color.
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
     * Get color.
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set alias.
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
     * Get alias.
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    public function hasAlias(): bool
    {
        return null !== $this->alias;
    }

    public function getNewIndexName(): string
    {
        return \sprintf('%s_%s', $this->getAlias(), (new \DateTimeImmutable())->format('Ymd_His'));
    }

    /**
     * Set baseUrl.
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
     * Get baseUrl.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param string[] $circles
     */
    public function setCircles(array $circles): Environment
    {
        $this->circles = $circles;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getCircles(): array
    {
        return $this->circles;
    }

    /**
     * Set inDefaultSearch.
     *
     * @param bool $inDefaultSearch
     *
     * @return Environment
     */
    public function setInDefaultSearch($inDefaultSearch)
    {
        $this->inDefaultSearch = $inDefaultSearch;

        return $this;
    }

    /**
     * Get inDefaultSearch.
     *
     * @return bool
     */
    public function getInDefaultSearch()
    {
        return $this->inDefaultSearch;
    }

    /**
     * Set extra.
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
     * Get extra.
     *
     * @return string
     */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * Add contentTypesHavingThisAsDefault.
     *
     * @return Environment
     */
    public function addContentTypesHavingThisAsDefault(ContentType $contentTypesHavingThisAsDefault)
    {
        $this->contentTypesHavingThisAsDefault[] = $contentTypesHavingThisAsDefault;

        return $this;
    }

    /**
     * Remove contentTypesHavingThisAsDefault.
     */
    public function removeContentTypesHavingThisAsDefault(ContentType $contentTypesHavingThisAsDefault)
    {
        $this->contentTypesHavingThisAsDefault->removeElement($contentTypesHavingThisAsDefault);
    }

    /**
     * Get contentTypesHavingThisAsDefault.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getContentTypesHavingThisAsDefault()
    {
        return $this->contentTypesHavingThisAsDefault;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return Environment
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

    public function isUpdateReferrers(): bool
    {
        return $this->updateReferrers;
    }

    public function setUpdateReferrers(bool $updateReferrers): void
    {
        $this->updateReferrers = $updateReferrers;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @see https://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     *
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $json = new JsonClass(\get_object_vars($this), __CLASS__);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->removeProperty('alias');
        $json->removeProperty('indexes');
        $json->removeProperty('total');
        $json->removeProperty('counter');
        $json->removeProperty('deletedRevision');
        $json->removeProperty('revisions');
        $json->removeProperty('contentTypesHavingThisAsDefault');

        return $json;
    }

    public function getLabel(): string
    {
        if (null === $this->label) {
            $replaced = \preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $this->name);
            if (!\is_string($replaced)) {
                $replaced = $this->name;
            }

            return \ucfirst(\strtolower(\trim($replaced)));
        }

        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }
}
