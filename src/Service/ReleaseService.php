<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Core\Revision\Release\ReleaseRevisionType;
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

    public function addRevisionForPublish(Release $release, Revision $revision): void
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

        $release->addRevision($revision, ReleaseRevisionType::PUBLISH);

        $this->releaseRepository->create($release);
        $this->logger->notice('log.data.revision.added_to_release', [...['release' => $release->getName()], ...LogRevisionContext::read($revision)]);
    }

    public function addRevisionForUnpublish(Release $release, Revision $revision): void
    {
        if ($revision->getDraft()) {
            $this->logger->error('log.data.revision.can_not_add_draft_in_release', [...['release' => $release->getName()], ...LogRevisionContext::read($revision)]);

            return;
        }

        foreach ($release->getRevisions() as $releaseRevision) {
            if ($releaseRevision->getRevisionOuuid() === $revision->giveOuuid()) {
                $this->logger->notice('log.data.revision.already_in_release', [...['release' => $release->getName()], ...LogRevisionContext::read($revision)]);

                return;
            }
        }

        try {
            $this->dataService->getRevisionByEnvironment($revision->giveOuuid(), $revision->giveContentType(), $release->getEnvironmentTarget());
        } catch (\Throwable) {
            $this->logger->notice('log.data.revision.document_not_in_target', [...['target' => $release->getEnvironmentTarget()->getName()], ...LogRevisionContext::read($revision)]);

            return;
        }

        $release->addRevision($revision, ReleaseRevisionType::UNPUBLISH);

        $this->releaseRepository->create($release);
        $this->logger->notice('log.data.revision.added_to_release', [...['release' => $release->getName()], ...LogRevisionContext::read($revision)]);
    }

    /**
     * @param array<string> $emsLinks
     */
    public function addRevisions(Release $release, ReleaseRevisionType $type, array $emsLinks): void
    {
        foreach ($emsLinks as $emsLink) {
            $emsLinkObject = EMSLink::fromText($emsLink);
            $contentType = $this->contentTypeService->giveByName($emsLinkObject->getContentType());

            $environment = match ($type) {
                ReleaseRevisionType::PUBLISH => $release->getEnvironmentSource(),
                ReleaseRevisionType::UNPUBLISH => $release->getEnvironmentTarget(),
            };

            try {
                $revision = $this->dataService->getRevisionByEnvironment($emsLinkObject->getOuuid(), $contentType, $environment);
                $release->addRevision($revision, $type);
            } catch (\Throwable) {
                continue;
            }
        }

        $this->releaseRepository->create($release);
    }

    /**
     * @param array<string> $ids
     */
    public function removeRevisions(Release $release, array $ids): void
    {
        $revisionIds = \array_map('intval', $ids);

        foreach ($release->getRevisions() as $releaseRevision) {
            if (\in_array($releaseRevision->getId(), $revisionIds, true)) {
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

    public function executeRelease(Release $release, bool $command = false): void
    {
        if (Release::READY_STATUS !== $release->getStatus()) {
            $this->logger->error('log.service.release.not.ready', [
                'name' => $release->getName(),
            ]);

            return;
        }

        foreach ($release->getRevisions() as $releaseRevision) {
            match ($releaseRevision->getType()) {
                ReleaseRevisionType::PUBLISH => $this->executePublish($release, $releaseRevision),
                ReleaseRevisionType::UNPUBLISH => $this->executeUnpublish($release, $releaseRevision, $command),
            };
        }

        $release->setStatus(Release::APPLIED_STATUS);
        $this->update($release);
    }

    private function executePublish(Release $release, ReleaseRevision $releaseRevision): void
    {
        try {
            $rollbackRevision = $this->dataService->getRevisionByEnvironment(
                ouuid: $releaseRevision->getRevisionOuuid(),
                contentType: $releaseRevision->getContentType(),
                environment: $release->getEnvironmentTarget()
            );
        } catch (\Throwable) {
            $rollbackRevision = null;
        }

        $releaseRevision->setRollbackRevision($rollbackRevision);
        $this->publishService->publish($releaseRevision->getRevision(), $release->getEnvironmentTarget(), 'SYSTEM_RELEASE');
    }

    private function executeUnpublish(Release $release, ReleaseRevision $releaseRevision, bool $command): void
    {
        $this->publishService->unpublish($releaseRevision->getRevision(), $release->getEnvironmentTarget(), $command);
    }

    /**
     * @param string[] $ids
     */
    public function rollback(Release $release, array $ids): Release
    {
        $releaseRevisions = $this->releaseRevisionService->getByIds($ids);
        $rollback = new Release();
        $rollback->setEnvironmentSource($release->getEnvironmentSource());
        $rollback->setEnvironmentTarget($release->getEnvironmentTarget());
        $rollback->setName(\sprintf('Rollback "%s"', $release->getName()));
        $rollback->setStatus(Release::WIP_STATUS);

        foreach ($releaseRevisions as $releaseRevision) {
            match ($releaseRevision->getType()) {
                ReleaseRevisionType::PUBLISH => $this->rollBackPublish($rollback, $releaseRevision),
                ReleaseRevisionType::UNPUBLISH => $this->rollBackUnpublish($rollback, $releaseRevision),
            };
        }
        $this->update($rollback);

        return $rollback;
    }

    private function rollBackPublish(Release $rollBackRelease, ReleaseRevision $releaseRevision): void
    {
        $rollbackRevision = $releaseRevision->getRollbackRevision();

        if ($rollbackRevision) {
            $rollBackRelease->addRevision($rollbackRevision, ReleaseRevisionType::PUBLISH);
        } else {
            $rollBackRelease->addRevision($releaseRevision->getRevision(), ReleaseRevisionType::UNPUBLISH);
        }
    }

    private function rollBackUnpublish(Release $rollBackRelease, ReleaseRevision $releaseRevision): void
    {
        $rollBackRelease->addRevision($releaseRevision->getRevision(), ReleaseRevisionType::PUBLISH);
    }

    public function getById(int $id): Release
    {
        return $this->releaseRepository->getById($id);
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
