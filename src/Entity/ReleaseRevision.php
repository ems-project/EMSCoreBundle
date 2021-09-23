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
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Release
     * @ORM\ManyToOne(targetEntity="Release", inversedBy="revisions")
     * @ORM\JoinColumn(name="release_id", referencedColumnName="id")
     */
    private $release;

    /**
     * @var Revision|null
     * @ORM\ManyToOne(targetEntity="Revision")
     * @ORM\JoinColumn(name="revision_id", referencedColumnName="id", nullable=true)
     */
    private $revision;

    /**
     * @var string
     *
     * @ORM\Column(name="revision_ouuid", type="string", length=255)
     */
    private $revisionOuuid;

    /**
     * @var ContentType
     * @ORM\ManyToOne(targetEntity="ContentType")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    private $contentType;

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
}
