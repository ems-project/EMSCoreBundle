<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Common\ArrayHelper\RecursiveMapper;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Service\Mapping;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Revision.
 *
 * @ORM\Table(name="revision", uniqueConstraints={@ORM\UniqueConstraint(name="tuple_index", columns={"end_time", "ouuid"})})
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\RevisionRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Revision implements EntityInterface
{
    use RevisionTaskTrait;

    /**
     * @var int|null
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
     * @var \DateTime|null
     *
     * @ORM\Column(name="auto_save_at", type="datetime", nullable=true)
     */
    private $autoSaveAt;

    /**
     * @ORM\Column(name="archived", type="boolean", options={"default": false})
     */
    private bool $archived;

    /**
     * @var bool
     *
     * @ORM\Column(name="deleted", type="boolean")
     */
    private $deleted;

    /**
     * @var ContentType|null
     *
     * @ORM\ManyToOne(targetEntity="ContentType")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private $contentType;

    private $dataField;

    /**
     * @var int
     *
     * @ORM\Column(name="version", type="integer")
     * @ORM\Version
     */
    private $version;

    /**
     * @var string|null
     *
     * @ORM\Column(name="ouuid", type="string", length=255, nullable=true, options={"collation":"utf8_bin"})
     */
    private $ouuid;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="start_time", type="datetime")
     */
    private $startTime;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="end_time", type="datetime", nullable=true)
     */
    private $endTime;

    /**
     * @var bool
     *
     * @ORM\Column(name="draft", type="boolean")
     */
    private $draft;

    /**
     * @var string|null
     *
     * @ORM\Column(name="finalized_by", type="string", length=255, nullable=true)
     */
    private $finalizedBy;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="finalized_date", type="datetime", nullable=true)
     */
    private $finalizedDate;

    /**
     * @var \DateTime
     */
    private $tryToFinalizeOn;

    /**
     * @var string|null
     *
     * @ORM\Column(name="archived_by", type="string", length=255, nullable=true)
     */
    private $archivedBy;

    /**
     * @var string|null
     *
     * @ORM\Column(name="deleted_by", type="string", length=255, nullable=true)
     */
    private $deletedBy;

    /**
     * @var string|null
     *
     * @ORM\Column(name="lock_by", type="string", length=255, nullable=true)
     */
    private $lockBy;

    /**
     * @var string|null
     *
     * @ORM\Column(name="auto_save_by", type="string", length=255, nullable=true)
     */
    private $autoSaveBy;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="lock_until", type="datetime", nullable=true)
     */
    private $lockUntil;

    /**
     * @var ArrayCollection<int, Environment>|Environment[]
     *
     * @ORM\ManyToMany(targetEntity="Environment", inversedBy="revisions", cascade={"persist"})
     * @ORM\JoinTable(name="environment_revision")
     * @ORM\OrderBy({"orderKey":"ASC"})
     */
    private Collection $environments;

    /**
     * @ORM\OneToMany(targetEntity="Notification", mappedBy="revision", cascade={"persist", "remove"})
     * @ORM\OrderBy({"created" = "ASC"})
     */
    private $notifications;

    /**
     * @var array
     *
     * @ORM\Column(name="raw_data", type="json_array", nullable=true)
     */
    private $rawData;

    /**
     * @var array|null
     *
     * @ORM\Column(name="auto_save", type="json_array", nullable=true)
     */
    private $autoSave;

    /**
     * @var array
     *
     * @ORM\Column(name="circles", type="simple_array", nullable=true)
     */
    private $circles;

    /**
     * @ORM\Column(name="labelField", type="text", nullable=true)
     */
    private ?string $labelField = null;

    /**
     * @var string
     *
     * @ORM\Column(name="sha1", type="string", nullable=true)
     */
    private $sha1;

    /**not persisted field to ensure that they are all there after a submit */
    private $allFieldsAreThere;

    /**
     * @var UuidInterface|null
     *
     * @ORM\Column(type="uuid", name="version_uuid", unique=false, nullable=true)
     */
    private $versionUuid;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", name="version_tag", nullable=true)
     */
    private $versionTag;

    /**
     * @ORM\Column(name="draft_save_date", type="datetime", nullable=true)
     */
    private ?\DateTime $draftSaveDate;

    /**
     * @var Collection<int, ReleaseRevision>
     * @ORM\OneToMany(targetEntity="ReleaseRevision", mappedBy="revision", cascade={"remove"})
     */
    private Collection $releases;

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

        if (null == $this->lockBy || null == $this->lockUntil || new \DateTime() > $this->lockUntil) {
            throw new NotLockedException($this);
        }
    }

    /**
     * Add the virtual fields to the raw data and return it (the data).
     *
     * @return array
     */
    public function getData()
    {
        return RawDataTransformer::transform($this->giveContentType()->getFieldType(), $this->rawData);
    }

    /**
     * Remove virtual fields ans save the raw data.
     *
     * @return \EMS\CoreBundle\Entity\Revision
     */
    public function setData(array $data)
    {
        $this->rawData = RawDataTransformer::reverseTransform($this->giveContentType()->getFieldType(), $data);

        return $this;
    }

    public function buildObject()
    {
        return [
            '_id' => $this->ouuid,
            '_type' => $this->giveContentType()->getName(),
            '_source' => $this->rawData,
        ];
    }

    public function __construct()
    {
        $this->archived = false;
        $this->deleted = false;
        $this->allFieldsAreThere = false;
        $this->environments = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->releases = new ArrayCollection();

        $a = \func_get_args();
        $i = \func_num_args();
        if (1 == $i) {
            if ($a[0] instanceof Revision) {
                /** @var Revision $ancestor */
                $ancestor = $a[0];
                $this->deleted = $ancestor->deleted;
                $this->draft = true;
                $this->allFieldsAreThere = $ancestor->allFieldsAreThere;
                $this->ouuid = $ancestor->ouuid;
                $this->contentType = $ancestor->contentType;
                $this->rawData = $ancestor->rawData;
                $this->circles = $ancestor->circles;
                $this->dataField = new DataField($ancestor->dataField);
                $this->owner = $ancestor->owner;
                $this->taskCurrent = $ancestor->taskCurrent;
                $this->taskPlannedIds = $ancestor->taskPlannedIds;
                $this->taskApprovedIds = $ancestor->taskApprovedIds;

                if (null !== $versionUuid = $ancestor->getVersionUuid()) {
                    $this->setVersionId($versionUuid);
                }
                if (null !== $versionTag = $ancestor->getVersionTag()) {
                    $this->setVersionTag($versionTag);
                }
            }
        }
        //TODO: Refactoring: Dependency injection of the first Datafield in the Revision.
    }

    public function __toString()
    {
        $out = 'New instance';
        if ($this->ouuid) {
            $out = $this->ouuid;
        }
        if (null !== $this->contentType) {
            $out = $this->contentType->getName().':'.$out;
            if (!empty($this->id)) {
                $out .= '#'.$this->id;
            }
        }

        if (null !== $this->contentType && $this->contentType->getLabelField() && $this->rawData && isset($this->rawData[$this->contentType->getLabelField()])) {
            return $this->rawData[$this->contentType->getLabelField()]." ($out)";
        }

        return $out;
    }

    public function getObject($object)
    {
        $object = [
                '_index' => 'N/A',
                '_source' => $object,
                '_id' => $this->ouuid,
                '_type' => $this->giveContentType()->getName(),
        ];

        return $object;
    }

    /**
     * Create a draft from a revision.
     *
     * @return Revision
     */
    public function convertToDraft()
    {
        $draft = clone $this;
        $draft->environments = new ArrayCollection();

        $now = new \DateTime('now');
        $draft->addEnvironment($this->giveContentType()->giveEnvironment());
        $draft->setStartTime($now);
        $draft->setCreated($now);
        $draft->setEndTime(null);
        $draft->setAutoSave(null);
        $draft->setDraft(true);

        return $draft;
    }

    public function clone(): self
    {
        $clone = clone $this;
        $clone->id = null;
        $clone->ouuid = null;
        $clone->autoSaveAt = null;
        $clone->autoSaveBy = null;
        $clone->autoSave = null;
        $clone->lockBy = null;
        $clone->lockUntil = null;
        $clone->finalizedBy = null;
        $clone->finalizedDate = null;
        $clone->startTime = new \DateTime('now');
        $clone->environments = new ArrayCollection(); //clear publications
        $clone->notifications = new ArrayCollection(); //clear notifications

        return $clone;
    }

    /**
     * Close a revision.
     */
    public function close(\DateTime $endTime)
    {
        $this->setEndTime($endTime);
        $this->setDraft(false);
        $this->setAutoSave(null);
        $this->removeEnvironment($this->giveContentType()->getEnvironment());
    }

    /**
     * Get allFieldAreThere.
     *
     * @return bool
     */
    public function getAllFieldsAreThere()
    {
        return $this->allFieldsAreThere;
    }

    public function setAllFieldsAreThere($allFieldsAreThere)
    {
        $this->allFieldsAreThere = !empty($allFieldsAreThere);

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return Revision
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
     * @return Revision
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

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): self
    {
        $this->archived = $archived;

        return $this;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * Set ouuid.
     *
     * @param string $ouuid
     *
     * @return Revision
     */
    public function setOuuid($ouuid)
    {
        $this->ouuid = $ouuid;

        return $this;
    }

    /**
     * Get ouuid.
     *
     * @return string
     */
    public function getOuuid()
    {
        return $this->ouuid;
    }

    public function giveOuuid(): string
    {
        if (null === $this->ouuid) {
            throw new \RuntimeException('Revision has no ouuid!');
        }

        return $this->ouuid;
    }

    public function hasOuuid(): bool
    {
        return null !== $this->ouuid;
    }

    public function getEmsId(): string
    {
        return \sprintf('%s:%s', $this->giveContentType(), $this->giveOuuid());
    }

    public function getEmsLink(): string
    {
        return \sprintf('ems://object:%s:%s', $this->giveContentType(), $this->giveOuuid());
    }

    /**
     * Set startTime.
     *
     * @param \DateTime $startTime
     *
     * @return Revision
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    /**
     * Get startTime.
     *
     * @return \DateTime
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set endTime.
     *
     * @param \DateTime|null $endTime
     *
     * @return Revision
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function hasEndTime(): bool
    {
        return null !== $this->endTime;
    }

    /**
     * Get endTime.
     *
     * @return \DateTime|null
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * Set draft.
     *
     * @param bool $draft
     *
     * @return Revision
     */
    public function setDraft($draft)
    {
        $this->draft = $draft;

        return $this;
    }

    /**
     * Get draft.
     *
     * @return bool
     */
    public function getDraft()
    {
        return $this->draft;
    }

    /**
     * Set lockBy.
     *
     * @param string $lockBy
     *
     * @return Revision
     */
    public function setLockBy($lockBy)
    {
        $this->lockBy = $lockBy;

        return $this;
    }

    public function getLockBy(): ?string
    {
        return $this->lockBy;
    }

    public function isLockedFor(string $username): bool
    {
        return $this->getLockBy() !== $username && $this->isLocked();
    }

    public function isLocked(): bool
    {
        $now = new \DateTime();

        return $now < $this->getLockUntil();
    }

    /**
     * Set rawDataFinalizedBy.
     *
     * @param string $finalizedBy
     *
     * @return Revision
     */
    public function setRawDataFinalizedBy($finalizedBy)
    {
        $this->rawData[Mapping::FINALIZED_BY_FIELD] = $finalizedBy;
        $this->tryToFinalizeOn = new \DateTime();
        $this->rawData[Mapping::FINALIZATION_DATETIME_FIELD] = ($this->tryToFinalizeOn)->format(\DateTime::ISO8601);

        return $this;
    }

    /**
     * Set finalizedBy.
     *
     * @param string $finalizedBy
     *
     * @return Revision
     */
    public function setFinalizedBy($finalizedBy)
    {
        $this->finalizedBy = $finalizedBy;
        $this->finalizedDate = $this->tryToFinalizeOn;

        return $this;
    }

    /**
     * Get finalizedBy.
     *
     * @return string
     */
    public function getFinalizedBy()
    {
        return $this->finalizedBy;
    }

    public function getArchivedBy(): ?string
    {
        return $this->archivedBy;
    }

    public function setArchivedBy(?string $archivedBy): void
    {
        $this->archivedBy = $archivedBy;
    }

    public function setDeletedBy(?string $deletedBy): self
    {
        $this->deletedBy = $deletedBy;

        return $this;
    }

    public function getDeletedBy(): ?string
    {
        return $this->deletedBy;
    }

    /**
     * Set lockUntil.
     *
     * @param \DateTime $lockUntil
     *
     * @return Revision
     */
    public function setLockUntil($lockUntil)
    {
        $this->lockUntil = $lockUntil;

        return $this;
    }

    /**
     * Get lockUntil.
     *
     * @return \DateTime
     */
    public function getLockUntil()
    {
        return $this->lockUntil;
    }

    /**
     * Set contentType.
     *
     * @param ContentType $contentType
     *
     * @return Revision
     */
    public function setContentType(ContentType $contentType = null)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Get contentType.
     *
     * @return ContentType|null
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    public function giveContentType(): ContentType
    {
        if (null === $this->contentType) {
            throw new \RuntimeException('No contentType for revision!');
        }

        return $this->contentType;
    }

    public function getContentTypeName(): string
    {
        if (null === $this->contentType) {
            throw new \RuntimeException('No contentType for revision!');
        }

        return $this->contentType->getName();
    }

    /**
     * Set version.
     *
     * @param int $version
     *
     * @return Revision
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version.
     *
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set dataField.
     *
     * @param \EMS\CoreBundle\Entity\DataField $dataField
     *
     * @return Revision
     */
    public function setDataField(DataField $dataField = null)
    {
        $this->dataField = $dataField;

        return $this;
    }

    /**
     * Get dataField.
     *
     * @return \EMS\CoreBundle\Entity\DataField
     */
    public function getDataField()
    {
        return $this->dataField;
    }

    /**
     * Add environment.
     *
     * @return Revision
     */
    public function addEnvironment(Environment $environment)
    {
        $this->environments[] = $environment;

        return $this;
    }

    public function removeEnvironment(Environment $environment): self
    {
        $this->environments->removeElement($environment);

        return $this;
    }

    /**
     * @return Collection<int, Environment>
     */
    public function getEnvironments()
    {
        return $this->environments;
    }

    public function isPublished(string $environmentName): bool
    {
        foreach ($this->environments as $environment) {
            if ($environment->getName() === $environmentName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set rawData.
     *
     * @param array $rawData
     *
     * @return Revision
     */
    public function setRawData($rawData)
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * Get rawData.
     *
     * @return array
     */
    public function getRawData()
    {
        $rawData = $this->rawData ?? [];

        if (null !== $this->versionUuid) {
            $rawData[Mapping::VERSION_UUID] = $this->versionUuid;
        }

        return $rawData;
    }

    public function getHash(): string
    {
        $hash = $this->rawData[Mapping::HASH_FIELD] ?? null;

        if (null === $hash) {
            throw new \RuntimeException('Hash field not found in raw data!');
        }

        return $hash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCopyRawData(): array
    {
        if (null === $contentType = $this->getContentType()) {
            throw new \RuntimeException('content type not found!');
        }

        $rawData = $this->getRawData();
        $clearProperties = $contentType->getClearOnCopyProperties();

        RecursiveMapper::mapPropertyValue($rawData, function (string $property, $value) use ($clearProperties) {
            if (\in_array($property, $clearProperties, true)) {
                return null;
            }

            return $value;
        });

        return $rawData;
    }

    /**
     * Set autoSaveAt.
     *
     * @param \DateTime $autoSaveAt
     *
     * @return Revision
     */
    public function setAutoSaveAt($autoSaveAt)
    {
        $this->autoSaveAt = $autoSaveAt;

        return $this;
    }

    /**
     * Get autoSaveAt.
     *
     * @return \DateTime
     */
    public function getAutoSaveAt()
    {
        return $this->autoSaveAt;
    }

    /**
     * Set autoSaveBy.
     *
     * @param string $autoSaveBy
     *
     * @return Revision
     */
    public function setAutoSaveBy($autoSaveBy)
    {
        $this->autoSaveBy = $autoSaveBy;

        return $this;
    }

    /**
     * Get autoSaveBy.
     *
     * @return string
     */
    public function getAutoSaveBy()
    {
        return $this->autoSaveBy;
    }

    /**
     * Set autoSave.
     *
     * @param array|null $autoSave
     *
     * @return Revision
     */
    public function setAutoSave($autoSave)
    {
        $this->autoSave = $autoSave;

        return $this;
    }

    /**
     * Get autoSave.
     *
     * @return array
     */
    public function getAutoSave()
    {
        return $this->autoSave;
    }

    public function getLabel(): string
    {
        if (null !== $labelField = $this->getLabelField()) {
            return $labelField;
        }

        $contentType = $this->giveContentType();
        $contentTypeLabelField = $contentType->getLabelField();

        if (null === $contentTypeLabelField) {
            return '';
        }

        $label = $this->rawData[$contentTypeLabelField] ?? null;
        if (null !== $label) {
            return $label;
        }

        $label = $this->autoSave[$contentTypeLabelField] ?? null;
        if ($this->draft && null !== $label) {
            return $label;
        }

        return '';
    }

    public function setLabelField(?string $labelField): self
    {
        $this->labelField = $labelField;

        return $this;
    }

    public function getLabelField(): ?string
    {
        return $this->labelField;
    }

    /**
     * Set sha1.
     *
     * @param string $sha1
     *
     * @return Revision
     */
    public function setSha1($sha1)
    {
        $this->sha1 = $sha1;

        return $this;
    }

    /**
     * Get sha1.
     *
     * @return string
     */
    public function getSha1()
    {
        return $this->sha1;
    }

    /**
     * Set circles.
     *
     * @param array $circles
     *
     * @return Revision
     */
    public function setCircles($circles)
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
     * Add notification.
     *
     * @return Revision
     */
    public function addNotification(Notification $notification)
    {
        $this->notifications[] = $notification;

        return $this;
    }

    /**
     * Remove notification.
     */
    public function removeNotification(Notification $notification)
    {
        $this->notifications->removeElement($notification);
    }

    /**
     * Get notifications.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * @return \DateTime
     */
    public function getFinalizedDate()
    {
        return $this->finalizedDate;
    }

    /**
     * @return Revision
     */
    public function setFinalizedDate(\DateTime $finalizedDate)
    {
        $this->finalizedDate = $finalizedDate;

        return $this;
    }

    public function hasVersionTags(): bool
    {
        return $this->contentType ? $this->contentType->hasVersionTags() : false;
    }

    public function getVersionUuid(): ?UuidInterface
    {
        return $this->versionUuid;
    }

    public function getVersionDate(string $field): ?\DateTimeImmutable
    {
        if (null === $contentType = $this->contentType) {
            throw new \RuntimeException(\sprintf('ContentType not found for revision %d', $this->getId()));
        }

        $dateString = null;
        if ('from' === $field && null !== $dateFromField = $contentType->getVersionDateFromField()) {
            $dateString = $this->rawData[$dateFromField] ?? null;
        }
        if ('to' === $field && null !== $dateToField = $contentType->getVersionDateToField()) {
            $dateString = $this->rawData[$dateToField] ?? null;
        }

        if (null === $dateString) {
            return null;
        }

        $dateTime = \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $dateString);

        return $dateTime ? $dateTime : null;
    }

    public function getVersionTag(): ?string
    {
        return $this->versionTag;
    }

    /**
     * Called on initNewDraft or updateMetaFieldCommand.
     */
    public function setVersionMetaFields(): void
    {
        if (!$this->hasVersionTags()) {
            return;
        }

        if (null === $this->getVersionUuid()) {
            $versionId = isset($this->rawData['_version_uuid']) ? Uuid::fromString($this->rawData['_version_uuid']) : Uuid::uuid4();
            $this->setVersionId($versionId);
        }
        if (null === $this->getVersionTag()) {
            $this->setVersionTagDefault();
        }

        if (null === $this->getVersionDate('from') && null === $this->getVersionDate('to')) {
            if ($this->hasOuuid()) {
                $this->setVersionDate('from', \DateTimeImmutable::createFromMutable($this->created)); //migration existing docs
            } else {
                $this->setVersionDate('from', new \DateTimeImmutable('now'));
            }
        }
    }

    public function setVersionId(UuidInterface $versionUuid): void
    {
        $this->versionUuid = $versionUuid;
    }

    private function setVersionTagDefault(): void
    {
        $versionTags = $this->contentType ? $this->contentType->getVersionTags() : [];

        if (!isset($versionTags[0]) || !\is_string($versionTags[0])) {
            throw new \RuntimeException(\sprintf('No version tags found for contentType %s (use hasVersionTags)', $this->getContentTypeName()));
        }

        $this->setVersionTag($versionTags[0]);
    }

    public function setVersionTag(string $versionTag): void
    {
        $versionTags = $this->contentType ? $this->contentType->getVersionTags() : [];

        if (\in_array($versionTag, $versionTags)) {
            $this->versionTag = $versionTag;
        }
    }

    public function setVersionDate(string $field, \DateTimeImmutable $date): void
    {
        if (null === $contentType = $this->contentType) {
            throw new \RuntimeException(\sprintf('ContentType not found for revision %d', $this->getId()));
        }

        if ('from' === $field && null !== $dateFromField = $contentType->getVersionDateFromField()) {
            $this->rawData[$dateFromField] = $date->format(\DateTimeImmutable::ATOM);
        }

        if ('to' === $field && null !== $dateToField = $contentType->getVersionDateToField()) {
            $this->rawData[$dateToField] = $date->format(\DateTimeImmutable::ATOM);
        }
    }

    public function getDraftSaveDate(): ?\DateTime
    {
        return $this->draftSaveDate;
    }

    public function setDraftSaveDate(?\DateTime $draftSaveDate): void
    {
        $this->draftSaveDate = $draftSaveDate;
    }

    /**
     * @return ReleaseRevision[]
     */
    public function getReleases(): array
    {
        return $this->releases->toArray();
    }
}
