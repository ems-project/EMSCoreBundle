<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\ORM\NoResultException;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\ReleaseRepository;
use Psr\Log\LoggerInterface;

final class ReleaseService implements EntityServiceInterface
{
    public function __construct(private readonly ReleaseRepository $releaseRepository, private readonly ContentTypeService $contentTypeService, private readonly DataService $dataService, private readonly ReleaseRevisionService $releaseRevisionService, private readonly PublishService $publishService, private readonly LoggerInterface $logger)
    {
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

    public function addRevision(Release $release, Revision $revision): void
    {
        if ($revision->getDraft()) {
            $this->logger->error('log.data.revision.can_not_add_draft_in_release', [...['release' => $release->getName()], ...LogRevisionContext::read($revision)]);

            return;
        }
        foreach ($release->getRevisions() as $releaseRevision) {
            if ($releaseRevision->getRevisionOuuid() !== $revision->giveOuuid()) {
                continue;
            }
            if ($releaseRevision->getRevision() === $revision) {
                $this->logger->notice('log.data.revision.already_in_release', [...['release' => $release->getName()], ...LogRevisionContext::read($revision)]);

                return;
            }
            $releaseRevision->setRevision($revision);
            $this->releaseRepository->create($release);
            $this->logger->notice('log.data.revision.document_already_in_release_but_updated', [...['release' => $release->getName()], ...LogRevisionContext::read($revision)]);

            return;
        }
        $releaseRevision = new ReleaseRevision();
        $releaseRevision->setRelease($release);
        $releaseRevision->setContentType($revision->giveContentType());
        $releaseRevision->setRevisionOuuid($revision->giveOuuid());
        $releaseRevision->setRevision($revision);
        $release->addRevision($releaseRevision);
        $this->releaseRepository->create($release);
        $this->logger->notice('log.data.revision.added_to_release', [...['release' => $release->getName()], ...LogRevisionContext::read($revision)]);
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

            try {
                $revision = $this->dataService->getRevisionByEnvironment($emsLinkObject->getOuuid(), $contentType, $release->getEnvironmentSource());
            } catch (NoResultException) {
                $revision = null;
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
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
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
            } catch (NoResultException) {
                $revisionToRemove = null;
            }
            $releaseRevision->setRevisionBeforePublish($revisionToRemove);

            $revision = $releaseRevision->getRevision();
            if (null === $revision && null !== $revisionToRemove) {
                $this->publishService->unpublish($revisionToRemove, $release->getEnvironmentTarget(), $command);
            } elseif (null !== $revision) {
                $this->publishService->publish($revision, $release->getEnvironmentTarget(), 'SYSTEM_RELEASE');
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

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->releaseRepository->getById($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not supported for releases');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not supported for releases');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not supported for releases');
    }
}
