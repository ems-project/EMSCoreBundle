<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Common;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\Revision;

final class DocumentInfo
{
    private EMSLink $id;
    /** @var Revision[] */
    private array $revisions;
    private bool $editable;

    /**
     * @param Revision[] $revisions
     */
    public function __construct(EMSLink $id, array $revisions)
    {
        $this->id = $id;
        $this->revisions = $revisions;
        $this->editable = false;
    }

    public function getRevision(string $environmentName): ?Revision
    {
        foreach ($this->revisions as $revision) {
            foreach ($revision->getEnvironments() as $environment) {
                if ($environmentName === $environment->getName()) {
                    return $revision;
                }
            }
        }

        return null;
    }

    public function getCurrentRevision(): ?Revision
    {
        foreach ($this->revisions as $revision) {
            if (!$revision->hasEndTime()) {
                return $revision;
            }
        }

        return null;
    }

    public function isAligned(string $environmentName): bool
    {
        foreach ($this->revisions as $revision) {
            foreach ($revision->getEnvironments() as $environment) {
                if ($environmentName === $environment->getName()) {
                    return null === $revision->getEndTime();
                }
            }
        }

        return false;
    }

    public function isPublished(string $environmentName): bool
    {
        foreach ($this->revisions as $revision) {
            foreach ($revision->getEnvironments() as $environment) {
                if ($environmentName === $environment->getName()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasDraft(): bool
    {
        foreach ($this->revisions as $revision) {
            if ($revision->getDraft()) {
                return true;
            }
            if (null === $revision->getEndTime()) {
                return false;
            }
        }

        return true;
    }

    public function makeEditable(): void
    {
        $this->editable = true;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }
}
