<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Common;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;

final readonly class DocumentInfo
{
    /**
     * @param Revision[] $revisions
     */
    public function __construct(private EMSLink $id, private array $revisions)
    {
    }

    public function getId(): EMSLink
    {
        return $this->id;
    }

    public function getCurrentRevision(): ?Revision
    {
        return \array_find($this->revisions, fn ($revision) => null === $revision->getEndTime());
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

    public function getDefaultEnvironment(): ?Environment
    {
        foreach ($this->revisions as $revision) {
            return $revision->giveContentType()->giveEnvironment();
        }

        return null;
    }

    public function isAligned(string $environmentName): bool
    {
        if (null === $revision = $this->getRevision($environmentName)) {
            return false;
        }

        $defaultEnvironment = $revision->giveContentType()->giveEnvironment();

        if ($environmentName === $defaultEnvironment->getName() || $revision->isArchived()) {
            return true;
        }

        foreach ($revision->getEnvironments() as $environment) {
            if ($environment === $defaultEnvironment) {
                return true;
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

    public function getPublishedStatus(string $environmentName): string
    {
        if (!$this->isPublished($environmentName)) {
            return 'not_published';
        }

        if (!$this->isAligned($environmentName)) {
            return 'outdated';
        }

        return 'published';
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
}
