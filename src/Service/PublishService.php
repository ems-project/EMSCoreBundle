<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\NonUniqueResultException;
use EMS\CommonBundle\Helper\EmsFields;
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
    private Registry $doctrine;
    private AuthorizationCheckerInterface $authorizationChecker;

    private RevisionRepository $revRepository;
    private ContentTypeService $contentTypeService;
    private EnvironmentService $environmentService;
    private DataService $dataService;
    private UserService $userService;
    private EventDispatcherInterface $dispatcher;
    private LoggerInterface $logger;
    private LoggerInterface $auditLogger;
    private IndexService $indexService;
    private Bulker $bulker;

    public function __construct(
        Registry $doctrine,
        AuthorizationCheckerInterface $authorizationChecker,
        IndexService $indexService,
        ContentTypeService $contentTypeService,
        EnvironmentService $environmentService,
        DataService $dataService,
        UserService $userService,
        EventDispatcherInterface $dispatcher,
        LoggerInterface $logger,
        LoggerInterface $auditLogger,
        Bulker $bulker
    ) {
        $this->doctrine = $doctrine;
        $this->authorizationChecker = $authorizationChecker;
        $this->indexService = $indexService;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->dataService = $dataService;
        $this->userService = $userService;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->auditLogger = $auditLogger;
        $this->bulker = $bulker;

        /** @var RevisionRepository $revRepository */
        $revRepository = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Revision');
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

    public function bulkPublishStart(int $bulkSize): void
    {
        $this->bulker->setSize($bulkSize);
        $this->bulker->setLogger(new NullLogger());
        $this->bulker->setSign(false);
    }

    public function bulkPublish(Revision $revision, Environment $environment): int
    {
        if (!$revision->hasOuuid()) {
            throw new \RuntimeException('Draft revision passed to bulk publish!');
        }

        $logContext = LogRevisionContext::publish($revision, $environment);
        if ($revision->giveContentType()->giveEnvironment() === $environment && !$revision->hasEndTime()) {
            $this->logger->warning('service.publish.not_in_default_environment', $logContext);

            return 0;
        }

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

    public function bulkPublishFinished(): void
    {
        $this->bulker->send(true);
        $this->bulker->setLogger($this->logger);
        $this->bulker->setSign(true);
    }

    /**
     * @throws NonUniqueResultException
     * @throws DBALException
     */
    public function publish(Revision $revision, Environment $environment, bool $command = false): int
    {
        $logContext = LogRevisionContext::publish($revision, $environment);
        if (!$command && !$this->canPublish($revision, $environment)) {
            return 0;
        }

        $item = $this->revRepository->findByOuuidContentTypeAndEnvironment($revision, $environment);

        $already = false;
        if ($item === $revision) {
            $already = true;
            $this->logger->notice('service.publish.already_published', $logContext);
        } elseif ($item) {
            $this->revRepository->removeEnvironment($item, $environment);
        }

        if (!$command) {
            $this->dataService->lockRevision($revision, $environment);
        }

        $this->dataService->sign($revision, true);
        if ($this->indexService->indexRevision($revision, $environment)) {
            $this->revRepository->save($revision);
        } else {
            $this->logger->warning('service.publish.publish_failed', \array_merge([
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ], $logContext));
        }

        if (!$command) {
            $this->dataService->unlockRevision($revision);
        }

        if (!$already) {
            $this->revRepository->addEnvironment($revision, $environment);

            if (!$command) {
                $this->auditLogger->notice('log.published.success', \array_merge([
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                ], $logContext));
            }

            $this->dispatcher->dispatch(new RevisionPublishEvent($revision, $environment));
        }

        return $already ? 0 : 1;
    }

    public function publishAndAlignVersions(Revision $revision, Environment $environment): void
    {
        if (!$this->canPublish($revision, $environment)) {
            return;
        }

        if (null === $versionUuid = $revision->getVersionUuid()) {
            throw new \RuntimeException('Revision missing version uuid!');
        }

        $contentType = $revision->giveContentType();
        $defaultEnvironment = $contentType->giveEnvironment();
        $revisions = $this->revRepository->findAllByVersionUuid($versionUuid, $defaultEnvironment);

        foreach ($revisions as $revision) {
            $this->runAlignRevision($revision->giveOuuid(), $contentType, $defaultEnvironment, $environment);
        }
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

            if (!$this->authorizationChecker->isGranted($revision->giveContentType()->getPublishRole())) {
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
        $statement->execute();

        try {
            $this->indexService->delete($revision, $environment);
            $this->auditLogger->notice('log.unpublished.success', LogRevisionContext::unpublish($revision, $environment));

            $this->dispatcher->dispatch(new RevisionUnpublishEvent($revision, $environment));
        } catch (\Throwable $e) {
            if (!$revision->getDeleted()) {
                $this->logger->warning('service.publish.already_unpublished', LogRevisionContext::publish($revision, $environment));
            }
        }
    }

    private function canPublish(Revision $revision, Environment $environment): bool
    {
        $logContext = LogRevisionContext::publish($revision, $environment);

        $user = $this->userService->getCurrentUser();
        if (!empty($environment->getCircles()) && !$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT') && empty(\array_intersect($environment->getCircles(), $user->getCircles()))) {
            $this->logger->warning('service.publish.not_in_circles', $logContext);

            return false;
        }

        if (!$this->authorizationChecker->isGranted($revision->giveContentType()->getPublishRole())) {
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
        if ($contentType->giveEnvironment()->getName() === $environmentTarget->getName()) {
            $this->logger->warning('service.publish.not_in_default_environment', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ENVIRONMENT_FIELD => $environmentTarget,
            ]);

            return;
        }

        if (!$this->authorizationChecker->isGranted($contentType->getPublishRole())) {
            $this->logger->warning('service.publish.not_authorized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ENVIRONMENT_FIELD => $environmentTarget->getName(),
            ]);

            return;
        }

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
            $toClean = $this->revRepository->findByOuuidAndContentTypeAndEnvironment(
                $contentType,
                $ouuid,
                $environmentTarget
            );

            if ($toClean !== $revision) {
                if ($toClean) {
                    $this->unpublish($toClean, $environmentTarget);
                }
                $this->publish($revision, $environmentTarget);
            }
        }
    }
}
