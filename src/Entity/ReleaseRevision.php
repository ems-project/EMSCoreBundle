<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\IdentifierIntegerTrait;

class ReleaseRevision implements EntityInterface
{
    use IdentifierIntegerTrait;

    private Release $release;
    private ?Revision $revision = null;
    private ?Revision $revisionBeforePublish = null;
    private string $revisionOuuid;
    private ContentType $contentType;

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
