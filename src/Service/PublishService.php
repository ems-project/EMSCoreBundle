<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\NonUniqueResultException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\Environment\EnvironmentPublisher;
use EMS\CoreBundle\Core\Environment\EnvironmentPublisherFactory;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\RevisionPublishEvent;
use EMS\CoreBundle\Event\RevisionUnpublishEvent;
use EMS\CoreBundle\Repository\RevisionRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PublishService
{
    private readonly RevisionRepository $revRepository;

    public function __construct(
        private readonly Registry $doctrine,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly IndexService $indexService,
        private readonly ContentTypeService $contentTypeService,
        private readonly EnvironmentService $environmentService,
        private readonly EnvironmentPublisherFactory $environmentPublisherFactory,
        private readonly DataService $dataService,
        private readonly UserService $userService,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly LoggerInterface $auditLogger,
        private readonly Bulker $bulker
    ) {
        /** @var RevisionRepository $revRepository */
        $revRepository = $this->doctrine->getManager()->getRepository(Revision::class);
        $this->revRepository = $revRepository;
    }

    public function alignRevision(string $contentTypeName, string $ouuid, string $environmentSourceName, string $environmentTargetName): void
    {
        $contentType = $this->contentTypeService->giveByName($contentTypeName);
        $environmentSourceName = $this->environmentService->giveByName($environmentSourceName);
        $environmentTargetName = $this->environmentService->giveByName($environmentTargetName);

        $this->runAlignRevision($ouuid, $contentType, $environmentSourceName, $environmentTargetName);
    }

    public function silentPublish(Revision $revision): void
    {
        try {
            if (empty($revision->getOuuid())) {
                return;
            }

            $this->dataService->sign($revision, true);
            if ($this->indexService->indexRevision($revision)) {
                $this->logger->notice('service.publish.draft_published', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $revision->giveContentType()->giveEnvironment()->getName(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                ]);
            } else {
                $this->logger->warning('service.publish.draft_published_failed', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('service.publish.publish_draft_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $revision->giveContentType()->giveEnvironment()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }
    }

    public function silentUnpublish(Revision $revision, bool $flush = true): void
    {
        $environment = $revision->giveContentType()->giveEnvironment();
        $revision->removeEnvironment($environment);
        $this->indexService->delete($revision, $environment);

        if ($flush) {
            $this->doctrine->getManager()->persist($revision);
            $this->doctrine->getManager()->flush();
        }
    }

    public function bulkStart(int $bulkSize, ?LoggerInterface $logger = null): void
    {
        $this->bulker->setSize($bulkSize);
        $this->bulker->setLogger($logger ?? new NullLogger());
        $this->bulker->setSign(false);
    }

    /**
     * @return int 1 = published, 0 = already published, -1 block by template publication
     */
    public function bulkPublish(Revision $revision, Environment $environment, string $commandUser, bool $environmentPublisher = false): int
    {
        if (!$revision->hasOuuid()) {
            throw new \RuntimeException('Draft revision passed to bulk publish!');
        }

        $logContext = LogRevisionContext::publish($revision, $environment);
        if ($revision->giveContentType()->giveEnvironment() === $environment && !$revision->hasEndTime()) {
            $this->logger->warning('service.publish.not_in_default_environment', $logContext);

            return 0;
        }

        if ($environmentPublisher) {
            $publisher = $this->environmentPublisherFactory->create($environment, $revision);
            if ($publisher->blockPublication()) {
                return -1;
            }
        }

        $this->publishVersion($revision, $environment, $commandUser);

        $revisionEnvironment = $this->revRepository->findByOuuidContentTypeAndEnvironment($revision, $environment);
        $already = $revisionEnvironment === $revision;

        if (!$already && $revisionEnvironment) {
            $this->revRepository->removeEnvironment($revisionEnvironment, $environment);
        }
        if (!$already) {
            $this->revRepository->addEnvironment($revision, $environment);
        }

        $this->dataService->sign($revision, true);
        $contentTypeName = $revision->giveContentType()->getName();
        $rawData = $revision->getRawData();

        $this->bulker->index($contentTypeName, $revision->giveOuuid(), $environment->getAlias(), $rawData);

        return $already ? 0 : 1;
    }

    public function bulkUnpublish(Revision $revision, Environment $environment): void
    {
        $contentType = $revision->giveContentType();

        if ($contentType->giveEnvironment() === $environment) {
            throw new \LogicException('Unpublish failed: is default environment');
        }

        if (1 === $this->environmentService->getPublishedForRevision($revision)->count()) {
            throw new \LogicException('Unpublish failed: requires 1 environment');
        }

        $revision->getEnvironments()->removeElement($environment);
        $this->bulker->delete($environment->getAlias(), $revision->giveOuuid());
    }

    public function unpublishByContentType(ContentType $contentType): void
    {
        foreach ($this->environmentService->getEnvironments() as $environment) {
            $alias = $environment->getAlias();
            $ouuids = $this->revRepository->findAllOuuidsByContentTypeAndEnvironment($contentType, $environment);

            foreach ($ouuids as $ouuid) {
                $this->bulker->delete($alias, $ouuid);
            }

            $this->bulker->send(true);
        }
    }

    /**
     * @param string[] $ouuids
     */
    public function unpublishByOuuids(array $ouuids): void
    {
        foreach ($this->environmentService->getEnvironments() as $environment) {
            $alias = $environment->getAlias();
            foreach ($ouuids as $ouuid) {
                $this->bulker->delete($alias, $ouuid);
            }

            $this->bulker->send(true);
        }
    }

    public function bulkFinished(): void
    {
        $this->bulker->send(true);
        $this->bulker->setLogger($this->logger);
        $this->bulker->setSign(true);
    }

    public function getEnvironmentPublisher(Environment $environment): EnvironmentPublisher
    {
        return $this->environmentPublisherFactory->getPublisher($environment);
    }

    /**
     * @throws NonUniqueResultException
     * @throws DBALException
     */
    public function publish(Revision $revision, Environment $environment, ?string $commandUser = null, bool $canPublish = false): int
    {
        $logContext = LogRevisionContext::publish($revision, $environment);
        if (null === $commandUser && !$canPublish && !$this->canPublish($revision, $environment)) {
            return 0;
        }

        $this->publishVersion($revision, $environment, $commandUser);

        $item = $this->revRepository->findByOuuidContentTypeAndEnvironment($revision, $environment);

        $already = false;
        if ($item === $revision) {
            $already = true;
            $this->logger->notice('service.publish.already_published', $logContext);
        } elseif ($item) {
            $this->revRepository->removeEnvironment($item, $environment);
        }

        if (null === $commandUser) {
            $this->dataService->lockRevision($revision, $environment);
        }

        $this->dataService->sign($revision, true);
        if ($this->indexService->indexRevision($revision, $environment)) {
            $this->revRepository->save($revision);
        } else {
            $this->logger->warning('service.publish.publish_failed', [...[
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ], ...$logContext]);
        }

        if (null === $commandUser) {
            $this->dataService->unlockRevision($revision);
        }

        if (!$already) {
            $this->revRepository->addEnvironment($revision, $environment);

            if (null === $commandUser) {
                $this->auditLogger->notice('log.published.success', [...[
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                ], ...$logContext]);
            }

            $this->dispatcher->dispatch(new RevisionPublishEvent($revision, $environment));
        }

        return $already ? 0 : 1;
    }

    /**
     * @throws DBALException
     */
    public function unpublish(Revision $revision, Environment $environment, bool $command = false): void
    {
        if (!$command) {
            $user = $this->userService->getCurrentUser();
            if (!empty($environment->getCircles() && !$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT') && empty(\array_intersect($environment->getCircles(), $user->getCircles())))) {
                $this->logger->warning('service.publish.not_in_circles', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                ]);

                return;
            }

            if (!$this->authorizationChecker->isGranted($revision->giveContentType()->role(ContentTypeRoles::PUBLISH))) {
                $this->logger->warning('service.publish.not_authorized', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->giveOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    EmsFields::LOG_REVISION_ID_FIELD => $environment->getId(),
                ]);

                return;
            }
        }

        if ($revision->giveContentType()->giveEnvironment() === $environment) {
            $this->logger->warning('service.publish.not_in_default_environment', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                EmsFields::LOG_REVISION_ID_FIELD => $environment->getId(),
            ]);

            return;
        }

        /** @var Connection $connection */
        $connection = $this->doctrine->getConnection();
        $statement = $connection->prepare('delete from environment_revision where environment_id = :envId and revision_id = :revId');
        $statement->bindValue('envId', $environment->getId());
        $statement->bindValue('revId', $revision->getId());
        $statement->executeStatement();

        try {
            $this->indexService->delete($revision, $environment);
            $this->auditLogger->notice('log.unpublished.success', LogRevisionContext::unpublish($revision, $environment));

            $this->dispatcher->dispatch(new RevisionUnpublishEvent($revision, $environment));
        } catch (\Throwable) {
            if (!$revision->getDeleted()) {
                $this->logger->warning('service.publish.already_unpublished', LogRevisionContext::publish($revision, $environment));
            }
        }
    }

    private function canPublish(Revision $revision, Environment $environment): bool
    {
        $logContext = LogRevisionContext::publish($revision, $environment);

        $publisher = $this->environmentPublisherFactory->create($environment, $revision);
        foreach ($publisher->getRevisionMessages() as $message) {
            $this->logger->log($message['level'], $message['message']);
        }

        if ($publisher->blockPublication()) {
            return false;
        }

        $user = $this->userService->getCurrentUser();
        if (!empty($environment->getCircles()) && !$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT') && empty(\array_intersect($environment->getCircles(), $user->getCircles()))) {
            $this->logger->warning('service.publish.not_in_circles', $logContext);

            return false;
        }

        if (!$this->authorizationChecker->isGranted($revision->giveContentType()->role(ContentTypeRoles::PUBLISH))) {
            $this->logger->warning('service.publish.not_authorized', $logContext);

            return false;
        }

        if ($revision->giveContentType()->giveEnvironment() === $environment && !empty($revision->getEndTime())) {
            $this->logger->warning('service.publish.not_in_default_environment', $logContext);

            return false;
        }

        return true;
    }

    private function runAlignRevision(string $ouuid, ContentType $contentType, Environment $environmentSource, Environment $environmentTarget): void
    {
        $revision = $this->revRepository->findByOuuidAndContentTypeAndEnvironment(
            $contentType,
            $ouuid,
            $environmentSource
        );

        if (!$revision) {
            $this->logger->warning('service.publish.revision_not_found_in_source', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ENVIRONMENT_FIELD => $environmentTarget->getName(),
            ]);
        } else {
            $this->publish($revision, $environmentTarget);
        }
    }

    private function publishVersion(Revision $revision, Environment $environment, ?string $commandUser = null): void
    {
        if (!$revision->hasVersionTags()) {
            return;
        }

        if (null === $versionUuid = $revision->getVersionUuid()) {
            throw new \RuntimeException('Revision missing version uuid!');
        }

        $contentType = $revision->giveContentType();
        $selectedVersionTag = $revision->getVersionNextTag();

        if (\in_array($selectedVersionTag, [null, Revision::VERSION_BLANK], true)) {
            $revision->removeFromRawData($contentType->getVersionTagField());

            return;
        }

        $publishedRevision = $this->revRepository->findLatestVersion($contentType, $versionUuid, $environment);

        $now = new \DateTimeImmutable();
        $form = null;

        if ($publishedRevision && null === $revision->getVersionDate('to')) {
            $closedVersion = $publishedRevision->clone(); // create a new version
            $closedVersion->setEndTime(null);
            $closedVersion->setVersionDate('to', $now);
            $this->dataService->finalizeDraft($closedVersion, $form, $commandUser);

            $this->publish($closedVersion, $environment, $commandUser, true);

            $revision->setVersionDate('from', $now);
        }

        $revision->setVersionTag($selectedVersionTag);
        $revision->removeFromRawData($contentType->getVersionTagField());

        $this->dataService->finalizeDraft($revision, $form, $commandUser);
    }
}
