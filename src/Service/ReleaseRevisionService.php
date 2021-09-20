<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Repository\ReleaseRevisionRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Psr\Log\LoggerInterface;

final class ReleaseRevisionService implements QueryServiceInterface
{
    /** @var ReleaseRevisionRepository */
    private $releaseRevisionRepository;
    /** @var RevisionRepository */
    private $revisionRepository;
    /** @var LoggerInterface */
    private $logger;

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
        if (isset($context['option']) && TableAbstract::REMOVE_ACTION === $context['option']) {
            return $this->revisionRepository->getByIds($from, $size, $context);
        }

        return $this->revisionRepository->get($from, $size, $context);
    }

    public function getEntityName(): string
    {
        return 'revision';
    }

    /**
     * @param mixed $context
     */
    public function countQuery(string $searchValue = '', $context = null): int
    {
        if (isset($context['option']) && TableAbstract::REMOVE_ACTION === $context['option']) {
            return $this->revisionRepository->counterByIds($context);
        }

        return $this->revisionRepository->counter($context);
    }

    public function findToRemove(Release $release, string $ouuid, ContentType $contentType): ReleaseRevision
    {
        \dump($this->releaseRevisionRepository->findByReleasebyRevisionOuuidAndContentType($release, $ouuid, $contentType));

        return $this->releaseRevisionRepository->findByReleaseByRevisionOuuidAndContentType($release, $ouuid, $contentType);
    }
}
