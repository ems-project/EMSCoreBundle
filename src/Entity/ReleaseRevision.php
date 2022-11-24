<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="release_revision")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class ReleaseRevision implements EntityInterface
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\ManyToOne(targetEntity="Release", inversedBy="revisions")
     * @ORM\JoinColumn(name="release_id", referencedColumnName="id")
     */
    private Release $release;

    /**
     * @ORM\ManyToOne(targetEntity="Revision", inversedBy="releases")
     * @ORM\JoinColumn(name="revision_id", referencedColumnName="id", nullable=true)
     */
    private ?Revision $revision = null;

    /**
     * @ORM\ManyToOne(targetEntity="Revision")
     * @ORM\JoinColumn(name="revision_before_publish_id", referencedColumnName="id", nullable=true)
     */
    private ?Revision $revisionBeforePublish = null;

    /**
     * @ORM\Column(name="revision_ouuid", type="string", length=255)
     */
    private string $revisionOuuid;

    /**
     * @ORM\ManyToOne(targetEntity="ContentType")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private ContentType $contentType;

    public function getId(): int
    {
        return $this->id;
    }

    public function getRevisionOuuid(): string
    {
        return $this->revisionOuuid;
    }

    public function setRevisionOuuid(string $revisionOuuid): ReleaseRevision
    {
        $this->revisionOuuid = $revisionOuuid;

        return $this;
    }

    public function setRevision(?Revision $revision): ReleaseRevision
    {
        $this->revision = $revision;

        return $this;
    }

    public function getRevision(): ?Revision
    {
        return $this->revision;
    }

    public function setRelease(Release $release): ReleaseRevision
    {
        $this->release = $release;

        return $this;
    }

    public function getRelease(): Release
    {
        return $this->release;
    }

    public function setContentType(ContentType $contentType): ReleaseRevision
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    public function setRevisionBeforePublish(?Revision $revisionBeforePublish): void
    {
        $this->revisionBeforePublish = $revisionBeforePublish;
    }

    public function getRevisionBeforePublish(): ?Revision
    {
        return $this->revisionBeforePublish;
    }

    public function getEmsId(): string
    {
        return \implode(':', [$this->contentType->getName(), $this->revisionOuuid]);
    }

    public function getName(): string
    {
        return $this->getRevisionOuuid();
    }
}
