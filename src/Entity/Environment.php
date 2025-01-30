<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Core\Environment\Index;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;

class Environment extends JsonDeserializer implements \JsonSerializable, EntityInterface, \Stringable
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    protected string $name = '';
    protected ?string $label = null;
    protected string $alias = '';
    /** @var array<string, Index> */
    protected array $indexes = [];
    protected int $total = 0;
    /** @var int */
    protected $counter;
    /** @var int */
    protected $deletedRevision;
    /** @var string */
    protected $color;
    /** @var string */
    protected $baseUrl;
    /** @var bool */
    protected $managed;
    /** @var bool */
    protected $snapshot = false;
    /** @var Collection<int, Revision> */
    protected Collection $revisions;
    /** @var string[] */
    protected ?array $circles = null;
    /** @var bool */
    protected $inDefaultSearch;
    /** @var Collection<int, ContentType> */
    protected Collection $contentTypesHavingThisAsDefault;
    /** @var int */
    protected $orderKey;
    protected bool $updateReferrers = false;
    protected ?string $templatePublication = null;
    protected ?string $rolePublish = null;

    public function __construct()
    {
        $this->revisions = new ArrayCollection();
        $this->contentTypesHavingThisAsDefault = new ArrayCollection();

        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->name;
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

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param array<mixed> $indexes
     */
    public function setIndexes(array $indexes): self
    {
        $this->indexes = $indexes;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getTotal(): int
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

    public function addRevision(Revision $revision): self
    {
        $this->revisions[] = $revision;

        return $this;
    }

    public function removeRevision(Revision $revision): void
    {
        $this->revisions->removeElement($revision);
    }

    /**
     * @return Collection<int, Revision>
     */
    public function getRevisions(): Collection
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

    public function setAlias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getNewIndexName(): string
    {
        return \sprintf('%s_%s', $this->getAlias(), new \DateTime()->format('Ymd_His'));
    }

    public function getBuildDate(): ?\DateTimeInterface
    {
        $indexes = $this->indexes;

        if (1 === \count($indexes)) {
            return \array_shift($indexes)->getBuildDate();
        }

        return null;
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
        return $this->circles ?? [];
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

    public function addContentTypesHavingThisAsDefault(ContentType $contentTypesHavingThisAsDefault): self
    {
        $this->contentTypesHavingThisAsDefault[] = $contentTypesHavingThisAsDefault;

        return $this;
    }

    public function removeContentTypesHavingThisAsDefault(ContentType $contentTypesHavingThisAsDefault): void
    {
        $this->contentTypesHavingThisAsDefault->removeElement($contentTypesHavingThisAsDefault);
    }

    /**
     * @return Collection<int, ContentType>
     */
    public function getContentTypesHavingThisAsDefault(): Collection
    {
        return $this->contentTypesHavingThisAsDefault;
    }

    public function setOrderKey(int $orderKey): void
    {
        $this->orderKey = $orderKey;
    }

    public function hasOrderKey(): bool
    {
        return null !== $this->orderKey;
    }

    public function getOrderKey(): ?int
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

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
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

    public function getTemplatePublication(): ?string
    {
        return $this->templatePublication;
    }

    public function setTemplatePublication(?string $templatePublication): void
    {
        $this->templatePublication = $templatePublication;
    }

    public function getRolePublish(): ?string
    {
        return $this->rolePublish;
    }

    public function setRolePublish(?string $rolePublish): void
    {
        $this->rolePublish = $rolePublish;
    }
}
