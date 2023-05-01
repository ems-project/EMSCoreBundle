<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Common\Standard\Type;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Service\Mapping;
use EMS\Helpers\ArrayHelper\ArrayHelper;
use EMS\Helpers\Standard\DateTime;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Revision.
 *
 * @ORM\Table(name="revision", uniqueConstraints={@ORM\UniqueConstraint(name="tuple_index", columns={"end_time", "ouuid"})})
 *
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\RevisionRepository")
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Revision implements EntityInterface, \Stringable
{
    use RevisionTaskTrait;
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private ?int $id = null;
    /**
     * @ORM\Column(name="auto_save_at", type="datetime", nullable=true)
     */
    private ?\DateTime $autoSaveAt = null;
    /**
     * @ORM\Column(name="archived", type="boolean", options={"default": false})
     */
    private bool $archived = false;
    /**
     * @ORM\Column(name="deleted", type="boolean")
     */
    private bool $deleted = false;
    /**
     * @ORM\ManyToOne(targetEntity="ContentType")
     *
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private ?ContentType $contentType = null;
    private ?DataField $dataField = null;
    /**
     * @ORM\Column(name="version", type="integer")
     *
     * @ORM\Version
     */
    private int $version = 0;
    /**
     * @ORM\Column(name="ouuid", type="string", length=255, nullable=true)
     */
    private ?string $ouuid = null;
    /**
     * @ORM\Column(name="start_time", type="datetime")
     */
    private \DateTime $startTime;
    /**
     * @ORM\Column(name="end_time", type="datetime", nullable=true)
     */
    private ?\DateTime $endTime = null;
    /**
     * @ORM\Column(name="draft", type="boolean")
     */
    private bool $draft = false;
    /**
     * @ORM\Column(name="finalized_by", type="string", length=255, nullable=true)
     */
    private ?string $finalizedBy = null;
    /**
     * @ORM\Column(name="finalized_date", type="datetime", nullable=true)
     */
    private ?\DateTime $finalizedDate = null;
    private ?\DateTime $tryToFinalizeOn = null;
    /**
     * @ORM\Column(name="archived_by", type="string", length=255, nullable=true)
     */
    private ?string $archivedBy = null;
    /**
     * @ORM\Column(name="deleted_by", type="string", length=255, nullable=true)
     */
    private ?string $deletedBy = null;
    /**
     * @ORM\Column(name="lock_by", type="string", length=255, nullable=true)
     */
    private ?string $lockBy = null;
    /**
     * @ORM\Column(name="auto_save_by", type="string", length=255, nullable=true)
     */
    private ?string $autoSaveBy = null;
    /**
     * @ORM\Column(name="lock_until", type="datetime", nullable=true)
     */
    private ?\DateTime $lockUntil = null;
    /**
     * @var ArrayCollection<int, Environment>|Environment[]
     *
     * @ORM\ManyToMany(targetEntity="Environment", inversedBy="revisions", cascade={"persist"})
     *
     * @ORM\JoinTable(name="environment_revision")
     *
     * @ORM\OrderBy({"orderKey":"ASC"})
     */
    private Collection $environments;
    /**
     * @var Collection<int, Notification>
     *
     * @ORM\OneToMany(targetEntity="Notification", mappedBy="revision", cascade={"persist", "remove"})
     *
     * @ORM\OrderBy({"created" = "ASC"})
     */
    private Collection $notifications;
    /**
     * @var ?array<mixed>
     *
     * @ORM\Column(name="raw_data", type="json", nullable=true)
     */
    private ?array $rawData = null;
    /**
     * @var ?array<mixed>
     *
     * @ORM\Column(name="auto_save", type="json", nullable=true)
     */
    private ?array $autoSave = null;
    /**
     * @var ?string[]
     *
     * @ORM\Column(name="circles", type="simple_array", nullable=true)
     */
    private ?array $circles = null;
    /**
     * @ORM\Column(name="labelField", type="text", nullable=true)
     */
    private ?string $labelField = null;
    /**
     * @ORM\Column(name="sha1", type="string", nullable=true)
     */
    private ?string $sha1 = null;
    /**not persisted field to ensure that they are all there after a submit */
    private ?bool $allFieldsAreThere = false;
    /**
     * @ORM\Column(type="uuid", name="version_uuid", unique=false, nullable=true)
     */
    private ?UuidInterface $versionUuid = null;
    /**
     * @ORM\Column(type="string", name="version_tag", nullable=true)
     */
    private ?string $versionTag = null;
    /**
     * @ORM\Column(name="draft_save_date", type="datetime", nullable=true)
     */
    private ?\DateTime $draftSaveDate = null;
    /**
     * @var Collection<int, ReleaseRevision>
     *
     * @ORM\OneToMany(targetEntity="ReleaseRevision", mappedBy="revision", cascade={"remove"})
     */
    private Collection $releases;
    private bool $selfUpdate = false;

    public function enableSelfUpdate(): void
    {
        if ($this->getDraft()) {
            throw new LockedException($this);
        }

        $this->selfUpdate = true;
    }

    /**
     * @ORM\PrePersist
     *
     * @ORM\PreUpdate
     */
    public function checkLock(): void
    {
        if ($this->selfUpdate && $this->isLocked()) {
            throw new LockedException($this);
        }

        if (!$this->selfUpdate && !$this->isLockedBy()) {
            throw new NotLockedException($this);
        }
    }

    /**
     * Add the virtual fields to the raw data and return it (the data).
     *
     * @return array<mixed>
     */
    public function getData(): array
    {
        return RawDataTransformer::transform($this->giveContentType()->getFieldType(), $this->rawData ?? []);
    }

    /**
     * Remove virtual fields ans save the raw data.
     *
     * @param array<mixed> $data
     */
    public function setData(array $data): self
    {
        $this->rawData = RawDataTransformer::reverseTransform($this->giveContentType()->getFieldType(), $data);

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function buildObject(): array
    {
        return [
            '_id' => $this->ouuid,
            '_type' => $this->giveContentType()->getName(),
            '_source' => $this->rawData,
        ];
    }

    public function __construct()
    {
        $this->environments = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->releases = new ArrayCollection();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
        $this->startTime = new \DateTime('now');

        $a = \func_get_args();
        $i = \func_num_args();
        if (1 == $i) {
            if ($a[0] instanceof Revision) {
                $ancestor = $a[0];
                $this->deleted = $ancestor->deleted;
                $this->draft = true;
                $this->allFieldsAreThere = $ancestor->allFieldsAreThere;
                $this->ouuid = $ancestor->ouuid;
                $this->contentType = $ancestor->contentType;
                $this->rawData = $ancestor->rawData;
                $this->circles = $ancestor->circles;
                $this->dataField = new DataField($ancestor->dataField);
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
        // TODO: Refactoring: Dependency injection of the first Datafield in the Revision.
    }

    public function __toString(): string
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

    /**
     * @param array<mixed> $source
     *
     * @return array<mixed>
     */
    public function getObject($source): array
    {
        return [
                '_index' => 'N/A',
                '_source' => $source,
                '_id' => $this->ouuid,
                '_type' => $this->giveContentType()->getName(),
        ];
    }

    public function convertToDraft(): Revision
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
        $clone->environments = new ArrayCollection(); // clear publications
        $clone->notifications = new ArrayCollection(); // clear notifications

        return $clone;
    }

    /**
     * Close a revision.
     */
    public function close(\DateTime $endTime): void
    {
        if (null === $this->endTime) {
            $this->setEndTime($endTime);
        }
        $this->setDraft(false);
        $this->setAutoSave(null);
        $this->removeEnvironment($this->giveContentType()->giveEnvironment());
    }

    public function getAllFieldsAreThere(): ?bool
    {
        return $this->allFieldsAreThere;
    }

    public function setAllFieldsAreThere(?bool $allFieldsAreThere): self
    {
        $this->allFieldsAreThere = $allFieldsAreThere;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setOuuid(?string $ouuid): self
    {
        $this->ouuid = $ouuid;

        return $this;
    }

    public function getOuuid(): ?string
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

    public function setStartTime(\DateTime $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getStartTime(): \DateTime
    {
        return $this->startTime;
    }

    public function setEndTime(?\DateTime $endTime): self
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function hasEndTime(): bool
    {
        return null !== $this->endTime;
    }

    public function getEndTime(): ?\DateTime
    {
        return $this->endTime;
    }

    public function setDraft(bool $draft): self
    {
        $this->draft = $draft;

        return $this;
    }

    public function getDraft(): bool
    {
        return $this->draft;
    }

    public function setLockBy(string $lockBy): self
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

    public function isLockedBy(): bool
    {
        return null !== $this->lockBy && null !== $this->lockUntil && $this->isLocked();
    }

    public function isLocked(): bool
    {
        return DateTime::create('now') < $this->getLockUntil();
    }

    public function setRawDataFinalizedBy(string $finalizedBy): self
    {
        $this->rawData[Mapping::FINALIZED_BY_FIELD] = $finalizedBy;
        $this->tryToFinalizeOn = new \DateTime();
        $this->rawData[Mapping::FINALIZATION_DATETIME_FIELD] = $this->tryToFinalizeOn->format(\DateTime::ISO8601);

        return $this;
    }

    public function setFinalizedBy(string $finalizedBy): self
    {
        $this->finalizedBy = $finalizedBy;
        $this->finalizedDate = $this->tryToFinalizeOn;

        return $this;
    }

    public function getFinalizedBy(): ?string
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

    public function setLockUntil(\DateTime $lockUntil): self
    {
        $this->lockUntil = $lockUntil;

        return $this;
    }

    public function getLockUntil(): ?\DateTime
    {
        return $this->lockUntil;
    }

    public function setContentType(?ContentType $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getContentType(): ?ContentType
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

    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setDataField(?DataField $dataField): self
    {
        $this->dataField = $dataField;

        return $this;
    }

    public function getDataField(): ?DataField
    {
        return $this->dataField;
    }

    public function addEnvironment(Environment $environment): self
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
     * @param array<mixed> $rawData
     */
    public function setRawData(?array $rawData): self
    {
        $this->rawData = $rawData ?? [];

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getRawData(): array
    {
        $rawData = $this->rawData ?? [];

        if (null !== $this->versionUuid) {
            $rawData[Mapping::VERSION_UUID] = $this->versionUuid->toString();
        }

        return $rawData;
    }

    public function removeFromRawData(string $property): void
    {
        $rawData = $this->rawData ?? [];
        unset($rawData[$property]);

        $this->rawData = $rawData;
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
     * @return array<int|string, mixed>
     */
    public function getCopyRawData(): array
    {
        if (null === $contentType = $this->getContentType()) {
            throw new \RuntimeException('content type not found!');
        }

        $clearProperties = $contentType->getClearOnCopyProperties();

        return ArrayHelper::map($this->getRawData(), function ($value, $property) use ($clearProperties) {
            if (\in_array($property, $clearProperties, true)) {
                return null;
            }

            return $value;
        });
    }

    public function setAutoSaveAt(\DateTime $autoSaveAt): self
    {
        $this->autoSaveAt = $autoSaveAt;

        return $this;
    }

    public function getAutoSaveAt(): ?\DateTime
    {
        return $this->autoSaveAt;
    }

    public function setAutoSaveBy(string $autoSaveBy): self
    {
        $this->autoSaveBy = $autoSaveBy;

        return $this;
    }

    public function getAutoSaveBy(): ?string
    {
        return $this->autoSaveBy;
    }

    /**
     * @param array<mixed> $autoSave
     */
    public function setAutoSave(?array $autoSave): self
    {
        $this->autoSave = $autoSave;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getAutoSave(): ?array
    {
        return $this->autoSave;
    }

    public function getLabel(): string
    {
        $contentTypeLabelField = $this->giveContentType()->getLabelField();

        if (null === $contentTypeLabelField) {
            return $this->ouuid ?? '';
        }

        $label = $this->rawData[$contentTypeLabelField] ?? null;
        if (null !== $label) {
            return $label;
        }

        $label = $this->autoSave[$contentTypeLabelField] ?? null;
        if ($this->draft && null !== $label) {
            return $label;
        }

        return $this->ouuid ?? '';
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

    public function setSha1(string $sha1): self
    {
        $this->sha1 = $sha1;

        return $this;
    }

    public function getSha1(): ?string
    {
        return $this->sha1;
    }

    /**
     * @param ?string[] $circles
     */
    public function setCircles(?array $circles = null): self
    {
        $this->circles = $circles ?? [];

        return $this;
    }

    /**
     * @return string[]
     */
    public function getCircles(): array
    {
        return $this->circles ?? [];
    }

    public function addNotification(Notification $notification): self
    {
        $this->notifications[] = $notification;

        return $this;
    }

    public function removeNotification(Notification $notification): void
    {
        $this->notifications->removeElement($notification);
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function getFinalizedDate(): ?\DateTime
    {
        return $this->finalizedDate;
    }

    public function setFinalizedDate(\DateTime $finalizedDate): self
    {
        $this->finalizedDate = $finalizedDate;

        return $this;
    }

    public function hasVersionTags(): bool
    {
        return $this->contentType && $this->contentType->hasVersionTags();
    }

    public function getVersionUuid(): ?UuidInterface
    {
        return $this->versionUuid;
    }

    public function getVersionTagField(): ?string
    {
        $contentType = $this->giveContentType();

        return $contentType->hasVersionTagField() ? ($this->rawData[$contentType->getVersionTagField()] ?? null) : null;
    }

    public function getVersionDate(string $field): ?\DateTimeInterface
    {
        $contentType = $this->giveContentType();

        $dateString = null;
        if ('from' === $field && null !== $dateFromField = $contentType->getVersionDateFromField()) {
            $dateString = $this->rawData[$dateFromField] ?? null;
        }
        if ('to' === $field && null !== $dateToField = $contentType->getVersionDateToField()) {
            $dateString = $this->rawData[$dateToField] ?? null;
        }

        return $dateString ? DateTime::createFromFormat($dateString) : null;
    }

    public function hasVersionTag(): bool
    {
        return null !== $this->versionTag;
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

        $this->setVersionTag($this->rawData[Mapping::VERSION_TAG] ?? $this->getVersionTagDefault());

        if (null === $this->getVersionDate('from') && null === $this->getVersionDate('to')) {
            if ($this->hasOuuid()) {
                $this->setVersionDate('from', $this->created); // migration existing docs
            } else {
                $this->setVersionDate('from', new \DateTimeImmutable('now'));
            }
        }
    }

    public function setVersionId(UuidInterface $versionUuid): void
    {
        $this->versionUuid = $versionUuid;
    }

    private function getVersionTagDefault(): string
    {
        $versionTags = $this->contentType ? $this->contentType->getVersionTags() : [];

        if (!isset($versionTags[0])) {
            throw new \RuntimeException(\sprintf('No version tags found for contentType %s (use hasVersionTags)', $this->getContentTypeName()));
        }

        return Type::string($versionTags[0]);
    }

    public function setVersionTag(string $versionTag): void
    {
        $versionTags = $this->contentType ? $this->contentType->getVersionTags() : [];

        if (\in_array($versionTag, $versionTags)) {
            $this->versionTag = $versionTag;
        }
    }

    public function setVersionDate(string $field, \DateTimeInterface $date): void
    {
        if (null === $contentType = $this->contentType) {
            throw new \RuntimeException(\sprintf('ContentType not found for revision %d', $this->getId()));
        }

        if ('from' === $field && null !== $dateFromField = $contentType->getVersionDateFromField()) {
            $this->rawData[$dateFromField] = $date->format(\DateTimeInterface::ATOM);
        }

        if ('to' === $field && null !== $dateToField = $contentType->getVersionDateToField()) {
            $this->rawData[$dateToField] = $date->format(\DateTimeInterface::ATOM);
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

    public function getName(): string
    {
        return \strval($this->id ?? '');
    }
}
