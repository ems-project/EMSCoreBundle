<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Core\Revision\Release\ReleaseRevisionType;

/**
 * @ORM\Table(name="release_revision")
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class ReleaseRevision implements EntityInterface
{
    /**
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\ManyToOne(targetEntity="Revision")
     *
     * @ORM\JoinColumn(name="rollback_revision_id", referencedColumnName="id", nullable=true)
     */
    private ?Revision $rollbackRevision = null;

    /**
     * @ORM\Column(name="revision_ouuid", type="string", length=255)
     */
    private string $revisionOuuid;

    /**
     * @ORM\ManyToOne(targetEntity="ContentType")
     *
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private ContentType $contentType;

    /**
     * @ORM\Column(name="type", type="text")
     */
    private string $type;

    public function __construct(
        /**
         * @ORM\ManyToOne(targetEntity="Release", inversedBy="revisions")
         *
         * @ORM\JoinColumn(name="release_id", referencedColumnName="id")
         */
        private readonly Release $release,
        /**
         * @ORM\ManyToOne(targetEntity="Revision", inversedBy="releases")
         *
         * @ORM\JoinColumn(name="revision_id", referencedColumnName="id", nullable=false)
         */
        private Revision $revision,
        ReleaseRevisionType $type
    ) {
        $this->revisionOuuid = $this->revision->giveOuuid();
        $this->contentType = $this->revision->giveContentType();
        $this->type = $type->value;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRevisionOuuid(): string
    {
        return $this->revisionOuuid;
    }

    public function setRevision(Revision $revision): ReleaseRevision
    {
        $this->revision = $revision;

        return $this;
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }

    public function getRelease(): Release
    {
        return $this->release;
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    public function setRollbackRevision(?Revision $rollbackRevision): void
    {
        $this->rollbackRevision = $rollbackRevision;
    }

    public function getRollbackRevision(): ?Revision
    {
        return $this->rollbackRevision;
    }

    public function getEmsId(): string
    {
        return \implode(':', [$this->contentType->getName(), $this->revisionOuuid]);
    }

    public function getName(): string
    {
        return $this->getRevisionOuuid();
    }

    public function getType(): ReleaseRevisionType
    {
        return ReleaseRevisionType::from($this->type);
    }
}
