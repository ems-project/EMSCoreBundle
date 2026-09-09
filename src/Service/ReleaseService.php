<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Core\Revision\Release\ReleaseRevisionType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\ReleaseRepository;

use function Symfony\Component\Translation\t;

final readonly class ReleaseService implements EntityServiceInterface
{
    public function __construct(
        private ReleaseRepository $releaseRepository,
        private ContentTypeService $contentTypeService,
        private DataService $dataService,
        private ReleaseRevisionService $releaseRevisionService,
        private PublishService $publishService,
        private LocalizedLoggerInterface $logger
    ) {
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
            $this->logger->messageError(
                t('message.revision_draft_cannot_be_added_to_release', [], 'emsco-core'),
                [...LogRevisionContext::read($revision), 'release' => $release->getName()]
            );

            return;
        }
        foreach ($release->getRevisions() as $releaseRevision) {
            if ($releaseRevision->getRevisionOuuid() !== $revision->giveOuuid()) {
                continue;
            }
            if ($releaseRevision->getRevision() === $revision) {
                $this->logger->messageNotice(t('message.revision_already_in_release', [
                    'release' => $release->getName(),
                    'label' => $revision->getLabel(),
                ], 'emsco-core'));

                return;
            }
            $releaseRevision->setRevision($revision);
            $this->releaseRepository->create($release);

            $this->logger->messageNotice(t('message.revision_updated_in_release', [
                'release' => $release->getName(),
                'label' => $revision->getLabel(),
            ], 'emsco-core'));

            return;
        }

        $release->addRevision($revision, ReleaseRevisionType::PUBLISH);

        $this->releaseRepository->create($release);

        $this->logger->messageNotice(t('message.revision_added_to_release', [
            'release' => $release->getName(),
            'label' => $revision->getLabel(),
        ], 'emsco-core'));
    }

    public function addRevisionForUnpublish(Release $release, Revision $revision): void
    {
        if ($revision->getDraft()) {
            $this->logger->messageError(
                t('message.revision_draft_cannot_be_added_to_release', [], 'emsco-core'),
                [...LogRevisionContext::read($revision), 'release' => $release->getName()]
            );

            return;
        }

        foreach ($release->getRevisions() as $releaseRevision) {
            if ($releaseRevision->getRevisionOuuid() === $revision->giveOuuid()) {
                $this->logger->messageNotice(t('message.revision_already_in_release', [
                    'release' => $release->getName(),
                    'label' => $revision->getLabel(),
                ], 'emsco-core'));

                return;
            }
        }

        try {
            $this->dataService->getRevisionByEnvironment($revision->giveOuuid(), $revision->giveContentType(), $release->getEnvironmentTarget());
        } catch (\Throwable) {
            $this->logger->messageNotice(t('message.revision_not_in_target_environment', [
                'label' => $revision->getLabel(),
                'target' => $release->getEnvironmentTarget()->getLabel(),
            ], 'emsco-core'));

            return;
        }

        $release->addRevision($revision, ReleaseRevisionType::UNPUBLISH);

        $this->releaseRepository->create($release);
        $this->logger->messageNotice(t('message.revision_added_to_release', [
            'label' => $revision->getLabel(),
            'release' => $release->getName(),
        ], 'emsco-core'), LogRevisionContext::read($revision));
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
        $revisionIds = \array_map(intval(...), $ids);

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
        $this->logger->messageWarning(t('message.release_deleted', ['name' => $name], 'emsco-core'));
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

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @return Release[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if ($context instanceof Revision) {
            return $this->releaseRepository->getInWip($from, $size, $orderField, $orderDirection, $searchValue);
        }
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->releaseRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'release';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
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

    public function executeRelease(Release $release, ?string $userCommand = null): void
    {
        if (Release::READY_STATUS !== $release->getStatus()) {
            $this->logger->messageError(t('message.release_not_ready', ['name' => $release->getName()], 'emsco-core'));

            return;
        }

        foreach ($release->getRevisions() as $releaseRevision) {
            match ($releaseRevision->getType()) {
                ReleaseRevisionType::PUBLISH => $this->executePublish($release, $releaseRevision),
                ReleaseRevisionType::UNPUBLISH => $this->executeUnpublish($release, $releaseRevision, $userCommand),
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

    private function executeUnpublish(Release $release, ReleaseRevision $releaseRevision, ?string $userCommand = null): void
    {
        $this->publishService->unpublish($releaseRevision->getRevision(), $release->getEnvironmentTarget(), $userCommand);
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

        if ($rollbackRevision instanceof Revision) {
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

    #[\Override]
    public function getByItemName(string $name): EntityInterface
    {
        return $this->releaseRepository->getById($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not supported for releases');
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not supported for releases');
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not supported for releases');
    }
}
