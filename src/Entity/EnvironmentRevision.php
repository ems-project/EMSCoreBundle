<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\EntityInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class EnvironmentRevision implements EntityInterface
{
    private readonly UuidInterface $id;
    private Environment $environment;
    private Revision $revision;
    private \DateTime $created;
    private string $createdBy;
    private ?\DateTime $deleted = null;
    private ?string $deletedBy = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function setEnvironment(Environment $environment): void
    {
        $this->environment = $environment;
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }

    public function setRevision(Revision $revision): void
    {
        $this->revision = $revision;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTime $created): void
    {
        $this->created = $created;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getDeleted(): ?\DateTime
    {
        return $this->deleted;
    }

    public function setDeleted(?\DateTime $deleted): void
    {
        $this->deleted = $deleted;
    }

    public function getDeletedBy(): ?string
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(string $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }
}
