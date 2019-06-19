<?php

namespace EMS\CoreBundle\Service;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\NonUniqueResultException;
use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\RevisionPublishEvent;
use EMS\CoreBundle\Event\RevisionUnpublishEvent;
use EMS\CoreBundle\Repository\RevisionRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PublishService
{
    
    /**@var Registry $doctrine */
    protected $doctrine;
    /**@var AuthorizationCheckerInterface $authorizationChecker*/
    protected $authorizationChecker;
    /**@var TokenStorageInterface $tokenStorage*/
    protected $tokenStorage;
    protected $lockTime;
    /**@Client $client*/
    protected $client;
    /**@var Mapping $mapping*/
    protected $mapping;
    protected $instanceId;
    protected $em;
    /**@var RevisionRepository*/
    protected $revRepository;
    /**@var Session $session*/
    protected $session;
    /**@var ContentTypeService $contentTypeService*/
    protected $contentTypeService;
    /**@var EnvironmentService $environmentService*/
    protected $environmentService;

    /**@var DataService $dataService*/
    protected $dataService;
    
    /**@var UserService $userService*/
    protected $userService;
    
    /**@var EventDispatcherInterface*/
    protected $dispatcher;

    /**@var LoggerInterface*/
    protected $logger;
    
    
    public function __construct(
        Registry $doctrine,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        $lockTime,
        Client $client,
        Mapping $mapping,
        $instanceId,
        Session $session,
        ContentTypeService $contentTypeService,
        EnvironmentService $environmentService,
        DataService $dataService,
        UserService $userService,
        EventDispatcherInterface $dispatcher,
        LoggerInterface $logger
    ) {
        $this->doctrine = $doctrine;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->lockTime = $lockTime;
        $this->client = $client;
        $this->mapping = $mapping;
        $this->instanceId = $instanceId;
        $this->em = $this->doctrine->getManager();
        $this->revRepository = $this->em->getRepository('EMSCoreBundle:Revision');
        $this->session = $session;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->dataService = $dataService;
        $this->userService = $userService;
        $this->dispatcher= $dispatcher;
        $this->logger= $logger;
    }

    /**
     * @param string $type
     * @param string $ouuid
     * @param string $environmentSource
     * @param string $environmentTarget
     * @throws DBALException
     * @throws NonUniqueResultException
     */
    public function alignRevision($type, $ouuid, $environmentSource, $environmentTarget)
    {
        if ($this->contentTypeService->getByName($type)->getEnvironment()->getName() === $environmentTarget) {
            $this->logger->warning('service.publish.not_in_default_environment', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ENVIRONMENT_FIELD  => $environmentTarget,
            ]);
            return;
        }
        $contentType = $this->contentTypeService->getByName($type);
            
            
        if (! $this->authorizationChecker->isGranted($contentType->getPublishRole())) {
            $this->logger->warning('service.publish.not_authorized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ENVIRONMENT_FIELD  => $environmentTarget,
            ]);
            return;
        }
            
            
        $revision = $this->revRepository->findByOuuidAndContentTypeAndEnvironnement(
            $contentType,
            $ouuid,
            $this->environmentService->getByName($environmentSource)
        );
    
        if (!$revision) {
            $this->logger->warning('service.publish.revision_not_found_in_source', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ENVIRONMENT_FIELD  => $environmentTarget,
            ]);
        } else {
            $target = $this->environmentService->getByName($environmentTarget);
            
            $toClean = $this->revRepository->findByOuuidAndContentTypeAndEnvironnement(
                $contentType,
                $ouuid,
                $target
            );
            
            if ($toClean !== $revision) {
                if ($toClean) {
                    $this->unpublish($toClean, $target);
                }
                $this->publish($revision, $target);
            }
        }
    }

    public function silentPublish(Revision $revision)
    {
        try {
            if (empty($revision->getOuuid())) {
                return;
            }

            $body = $this->dataService->sign($revision, true);
            $index = $this->contentTypeService->getIndex($revision->getContentType());

            $body[Mapping::PUBLISHED_DATETIME_FIELD] = (new DateTime())->format(DateTime::ISO8601);
            $config =[
                'id' => $revision->getOuuid(),
                'index' => $index,
                'type' => $revision->getContentType()->getName(),
                'body' => $body,
            ];


            if ($revision->getContentType()->getHavePipelines()) {
                $config['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
            }

            $this->client->index($config);
            $this->logger->notice('service.publish.draft_published', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_ENVIRONMENT_FIELD  => $revision->getContentType()->getEnvironment()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ]);
        } catch (Exception $e) {
            $this->logger->warning('service.publish.publish_draft_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_ENVIRONMENT_FIELD  => $revision->getContentType()->getEnvironment()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }
    }

    /**
     * @param Revision $revision
     * @param Environment $environment
     * @param bool $command
     * @return int
     * @throws NonUniqueResultException
     * @throws DBALException
     */
    public function publish(Revision $revision, Environment $environment, $command = false)
    {
        if (!$command) {
            $user = $this->userService->getCurrentUser();
            if (!empty($environment->getCircles()) && !$this->authorizationChecker->isGranted('ROLE_ADMIN') && empty(array_intersect($environment->getCircles(), $user->getCircles()))) {
                $this->logger->warning('service.publish.not_in_circles', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                ]);
                return 0;
            }
            
            if (! $this->authorizationChecker->isGranted($revision->getContentType()->getPublishRole())) {
                $this->logger->warning('service.publish.not_authorized', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                ]);
                return 0;
            }
        }
        
        if ($revision->getContentType()->getEnvironment() === $environment && !empty($revision->getEndTime())) {
            $this->logger->warning('service.publish.not_in_default_environment', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
            ]);
            return 0;
        }

        $item = $this->revRepository->findByOuuidContentTypeAndEnvironnement($revision, $environment);
        
        $connection = $this->doctrine->getConnection();
        
        $already = false;
        if ($item === $revision) {
            $already = true;
            $this->logger->notice('service.publish.already_published', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                EmsFields::LOG_REVISION_ID_FIELD  => $environment->getId(),
            ]);
        } else if ($item) {
            /** @var Statement $statement */
            $statement = $connection->prepare("delete from environment_revision where environment_id = :envId and revision_id = :revId");
            $statement->bindValue('envId', $environment->getId());
            $statement->bindValue('revId', $item->getId());
            $statement->execute();
        }

        $body = $this->dataService->sign($revision);
        $index = $this->contentTypeService->getIndex($revision->getContentType(), $environment);

        $body[Mapping::PUBLISHED_DATETIME_FIELD] = (new DateTime())->format(DateTime::ISO8601);
        $config =[
                'id' => $revision->getOuuid(),
                'index' => $index,
                'type' => $revision->getContentType()->getName(),
                'body' => $body,
        ];
        
        
        if ($revision->getContentType()->getHavePipelines()) {
            $config['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
        }

        $this->client->index($config);
        
        if (!$already) {
            /** @var Statement $statement */
            $statement = $connection->prepare("insert into environment_revision (environment_id, revision_id) VALUES(:envId, :revId)");
            $statement->bindValue('envId', $environment->getId());
            $statement->bindValue('revId', $revision->getId());
            $statement->execute();
            if (!$command) {
                $this->logger->notice('service.publish.published', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                    EmsFields::LOG_REVISION_ID_FIELD  => $environment->getId(),
                ]);
            }

            $this->dispatcher->dispatch(RevisionPublishEvent::NAME, new RevisionPublishEvent($revision, $environment));
        }

        if (!$command) {
            $this->logger->info('log.data.revision.publish', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => $already?EmsFields::LOG_OPERATION_UPDATE:EmsFields::LOG_OPERATION_CREATE,
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);
        }
        
        return $already?0:1;
    }

    /**
     * @param Revision $revision
     * @param Environment $environment
     * @param bool $command
     * @throws DBALException
     */
    public function unpublish(Revision $revision, Environment $environment, $command = false)
    {
        
        if (!$command) {
            $user = $this->userService->getCurrentUser();
            if (!empty($environment->getCircles() && !$this->authorizationChecker->isGranted('ROLE_ADMIN') && empty(array_intersect($environment->getCircles(), $user->getCircles())))) {
                $this->logger->warning('service.publish.not_in_circles', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                ]);
                return;
            }
            
            
            if (! $this->authorizationChecker->isGranted($revision->getContentType()->getPublishRole())) {
                $this->logger->warning('service.publish.not_authorized', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                    EmsFields::LOG_REVISION_ID_FIELD  => $environment->getId(),
                ]);
                return;
            }
        }
        
        if ($revision->getContentType()->getEnvironment() === $environment) {
            $this->logger->warning('service.publish.not_in_default_environment', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                EmsFields::LOG_REVISION_ID_FIELD  => $environment->getId(),
            ]);
            return;
        }
        

        $connection = $this->doctrine->getConnection();
        /** @var Statement $statement */
        $statement = $connection->prepare("delete from environment_revision where environment_id = :envId and revision_id = :revId");
        $statement->bindValue('envId', $environment->getId());
        $statement->bindValue('revId', $revision->getId());
        $statement->execute();
        
        try {
            $this->client->delete([
                    'id' => $revision->getOuuid(),
                    'index' => $environment->getAlias(),
                    'type' => $revision->getContentType()->getName(),
            ]);
            $this->logger->notice('service.publish.unpublished', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                EmsFields::LOG_REVISION_ID_FIELD  => $environment->getId(),
            ]);

            $this->dispatcher->dispatch(RevisionUnpublishEvent::NAME, new RevisionUnpublishEvent($revision, $environment));
        } catch (Exception $e) {
            if (!$revision->getDeleted()) {
                $this->logger->warning('service.publish.already_unpublished', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_ENVIRONMENT_FIELD  => $environment->getName(),
                    EmsFields::LOG_REVISION_ID_FIELD  => $environment->getId(),
                ]);
            }
        }
        if (!$command) {
            $this->logger->info('log.data.revision.unpublish', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);
        }
    }
}
