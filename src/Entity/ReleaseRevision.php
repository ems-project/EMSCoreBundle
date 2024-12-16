<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Core\Revision\Release\ReleaseRevisionType;

class ReleaseRevision implements EntityInterface
{
    use IdentifierIntegerTrait;

    private ?Revision $rollbackRevision = null;
    private string $revisionOuuid;
    private ContentType $contentType;
    private string $type;

    public function __construct(
        private readonly Release $release,
        private Revision $revision,
        ReleaseRevisionType $type,
    ) {
        $this->revisionOuuid = $this->revision->giveOuuid();
        $this->contentType = $this->revision->giveContentType();
        $this->type = $type->value;
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
