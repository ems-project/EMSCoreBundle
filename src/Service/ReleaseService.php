<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\ORM\NoResultException;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\ReleaseRepository;
use Psr\Log\LoggerInterface;

final class ReleaseService implements EntityServiceInterface
{
    private ReleaseRepository $releaseRepository;
    private ContentTypeService $contentTypeService;
    private DataService $dataService;
    private ReleaseRevisionService $releaseRevisionService;
    private PublishService $publishService;
    private LoggerInterface $logger;

    public function __construct(ReleaseRepository $releaseRepository, ContentTypeService $contentTypeService, DataService $dataService, ReleaseRevisionService $releaseRevisionService, PublishService $publishService, LoggerInterface $logger)
    {
        $this->releaseRepository = $releaseRepository;
        $this->contentTypeService = $contentTypeService;
        $this->dataService = $dataService;
        $this->releaseRevisionService = $releaseRevisionService;
        $this->publishService = $publishService;
        $this->logger = $logger;
    }

    /**
     * @return Release[]
     */
    public function getAll(): array
    {
        return $this->releaseRepository->getAll();
    }

    public function add(Release $release): Release
    {
        $this->update($release);

        return $release;
    }

    public function update(Release $release): void
    {
        $this->releaseRepository->create($release);
    }

    /**
     * @param array<string> $emsLinks
     */
    public function addRevisions(Release $release, array $emsLinks): void
    {
        foreach ($emsLinks as $emsLink) {
            $emsLinkObject = EMSLink::fromText($emsLink);
            $releaseRevision = new ReleaseRevision();
            $releaseRevision->setRelease($release);
            $releaseRevision->setRevisionOuuid($emsLinkObject->getOuuid());

            $contentType = $this->contentTypeService->giveByName($emsLinkObject->getContentType());
            $releaseRevision->setContentType($contentType);
            $revision = null;

            if (!empty($release->getEnvironmentSource())) {
                try {
                    $revision = $this->dataService->getRevisionByEnvironment($emsLinkObject->getOuuid(), $contentType, $release->getEnvironmentSource());
                } catch (NoResultException $e) {
                    $revision = null;
                }
            }

            $releaseRevision->setRevision($revision);
            $release->addRevision($releaseRevision);
        }
        $this->releaseRepository->create($release);
    }

    /**
     * @param array<string> $ids
     */
    public function removeRevisions(Release $release, array $ids): void
    {
        foreach ($release->getRevisions() as $releaseRevision) {
            if (\in_array($releaseRevision->getId(), $ids)) {
                $this->releaseRevisionService->remove($releaseRevision);
            }
        }
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
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if ($context instanceof Revision) {
            return $this->releaseRepository->getInWip($from, $size, $orderField, $orderDirection, $searchValue);
        }
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->releaseRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'release';
    }

    /**
     * @param mixed $context
     */
    public function count(string $searchValue = '', $context = null): int
    {
        if ($context instanceof Revision) {
            return $this->releaseRepository->countWipReleases();
        }
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->releaseRepository->counter();
    }

    /**
     * @return Release[]
     */
    public function findReadyAndDue(): array
    {
        return $this->releaseRepository->findReadyAndDue();
    }

    public function publishRelease(Release $release, bool $command = false): void
    {
        if (Release::READY_STATUS !== $release->getStatus()) {
            $this->logger->error('log.service.release.not.ready', [
                'name' => $release->getName(),
            ]);

            return;
        }

        foreach ($release->getRevisions() as $releaseRevision) {
            try {
                $revisionToRemove = $this->dataService->getRevisionByEnvironment($releaseRevision->getRevisionOuuid(), $releaseRevision->getContentType(), $release->getEnvironmentTarget());
            } catch (NoResultException $e) {
                $revisionToRemove = null;
            }
            $releaseRevision->setRevisionBeforePublish($revisionToRemove);

            $revision = $releaseRevision->getRevision();
            if (null === $revision && null !== $revisionToRemove) {
                $this->publishService->unpublish($revisionToRemove, $release->getEnvironmentTarget(), $command);
            } elseif (null !== $revision) {
                $this->publishService->publish($revision, $release->getEnvironmentTarget(), $command);
            }
        }

        $release->setStatus(Release::APPLIED_STATUS);
        $this->update($release);
    }

    /**
     * @param string[] $ids
     */
    public function rollback(Release $release, array $ids): Release
    {
        $revisions = $this->releaseRevisionService->getByIds($ids);
        $rollback = new Release();
        $rollback->setEnvironmentSource($release->getEnvironmentSource());
        $rollback->setEnvironmentTarget($release->getEnvironmentTarget());
        $rollback->setName(\sprintf('Rollback "%s"', $release->getName()));
        $rollback->setStatus(Release::WIP_STATUS);
        foreach ($revisions as $revision) {
            $releaseRevision = new ReleaseRevision();
            $releaseRevision->setRelease($rollback);
            $releaseRevision->setRevisionBeforePublish(null);
            $releaseRevision->setRevision($revision->getRevisionBeforePublish());
            $releaseRevision->setContentType($revision->getContentType());
            $releaseRevision->setRevisionOuuid($revision->getRevisionOuuid());
            $release->addRevision($releaseRevision);
        }
        $this->update($rollback);

        return $rollback;
    }
}
