<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Repository\ReleaseRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Psr\Log\LoggerInterface;

final class ReleaseService implements EntityServiceInterface
{
    /** @var ReleaseRepository */
    private $releaseRepository;
    /** @var RevisionRepository */
    private $revisionRepository;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(ReleaseRepository $releaseRepository, RevisionRepository $revisionRepository, LoggerInterface $logger)
    {
        $this->releaseRepository = $releaseRepository;
        $this->revisionRepository = $revisionRepository;
        $this->logger = $logger;
    }

    /**
     * @return Release[]
     */
    public function getAll(): array
    {
        return $this->releaseRepository->getAll();
    }

    public function update(Release $release): void
    {
        $encoder = new Encoder();
        $name = $release->getName();
        if (null == $name) {
            throw new \RuntimeException('Unexpected null name');
        }
        $webalized = $encoder->webalize($name);
        if (null == $webalized) {
            throw new \RuntimeException('Unexpected null webalized name');
        }
        $release->setName($webalized);
        $this->releaseRepository->create($release);
    }

    /**
     * @param array<string> $ids
     */
    public function updateRevisions(Release $release, array $ids): void
    {
        $toAdd = \array_diff($ids, $release->getRevisionsIds());
        foreach ($toAdd as $id) {
            $revision = $this->revisionRepository->findOneById(\intval($id));
            $release->addRevision($revision);
        }

        $toRemove = \array_diff($release->getRevisionsIds(), $ids);
        foreach ($toRemove as $id) {
            $revision = $this->revisionRepository->findOneById(\intval($id));
            $release->removeRevision($revision);
        }

        $this->releaseRepository->create($release);
    }

    public function delete(Release $release): void
    {
        $name = $release->getName();
        $this->releaseRepository->delete($release);
        $this->logger->warning('log.service.release.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->releaseRepository->getByIds($ids) as $release) {
            $this->delete($release);
        }
    }

    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @param mixed $context
     *
     * @return Release[]
     */
    public function get(int $from, int $size, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->releaseRepository->get($from, $size);
    }

    public function getEntityName(): string
    {
        return 'release';
    }

    /**
     * @param mixed $context
     */
    public function count($context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->releaseRepository->counter();
    }
}
