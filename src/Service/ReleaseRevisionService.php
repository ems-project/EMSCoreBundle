<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Repository\ReleaseRevisionRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Psr\Log\LoggerInterface;

final class ReleaseRevisionService implements QueryServiceInterface, EntityServiceInterface
{
    public const QUERY_REVISIONS_IN_PUBLISHED_RELEASE = 'QUERY_REVISIONS_IN_PUBLISHED_RELEASE';
    public const QUERY_REVISIONS_IN_RELEASE = 'QUERY_REVISIONS_IN_RELEASE';
    private ReleaseRevisionRepository $releaseRevisionRepository;
    private RevisionRepository $revisionRepository;
    private LoggerInterface $logger;

    public function __construct(ReleaseRevisionRepository $releaseRevisionRepository, RevisionRepository $revisionRepository, LoggerInterface $logger)
    {
        $this->releaseRevisionRepository = $releaseRevisionRepository;
        $this->revisionRepository = $revisionRepository;
        $this->logger = $logger;
    }

    public function isQuerySortable(): bool
    {
        return false;
    }

    public function remove(ReleaseRevision $releaseRevision): void
    {
        $this->releaseRevisionRepository->delete($releaseRevision);
    }

    /**
     * @param mixed $context
     *
     * @return Revision[]
     */
    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (!$context instanceof Release) {
            throw new \RuntimeException('Unexpected release object');
        }

        return $this->revisionRepository->getRevisionsForRelease($from, $size, $context);
    }

    public function getEntityName(): string
    {
        return 'release_revision';
    }

    /**
     * @param mixed $context
     */
    public function countQuery(string $searchValue = '', $context = null): int
    {
        if (!$context instanceof Release) {
            throw new \RuntimeException('Unexpected release object');
        }

        return $this->revisionRepository->countDifferencesBetweenEnvironment($context->getEnvironmentSource()->getId(), $context->getEnvironmentTarget()->getId());
    }

    public function findToRemove(Release $release, string $ouuid, ContentType $contentType): ReleaseRevision
    {
        return $this->releaseRevisionRepository->findByReleaseByRevisionOuuidAndContentType($release, $ouuid, $contentType);
    }

    public function finalizeDraftEvent(RevisionFinalizeDraftEvent $event): void
    {
        $revision = $event->getRevision();
        $releaseRevisions = $this->releaseRevisionRepository->getRevisionsLinkedToReleasesByOuuid($revision->getOuuid(), $revision->getContentType());
        foreach ($releaseRevisions as $releaseRevision) {
            $this->logger->warning('log.service.release_revision.preceding.revision.in.release', [
                'name' => $releaseRevision->getRelease()->getName(),
            ]);
        }
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (!$context instanceof Release) {
            throw new \RuntimeException('Unexpected non Release context');
        }

        return $this->releaseRevisionRepository->findAll();
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (!$context instanceof Release) {
            throw new \RuntimeException('Unexpected non Release context');
        }

        return $this->releaseRevisionRepository->count([]);
    }
}
