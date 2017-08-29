<?php

namespace EMS\CoreBundle\Service;


use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\RevisionPublishEvent;
use EMS\CoreBundle\Event\RevisionUnpublishEvent;
use EMS\CoreBundle\Repository\RevisionRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
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

	/**@var AuditService $auditService*/
	protected $auditService;
	
	/**@var UserService $userService*/
	protected $userService;
	
	/**@var EventDispatcherInterface*/
	protected $dispatcher;
	
	
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
			AuditService $auditService,
			UserService $userService,
			EventDispatcherInterface $dispatcher)
	{
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
		$this->auditService = $auditService;
		$this->userService = $userService;
		$this->dispatcher= $dispatcher;
	}
	
	public function alignRevision($type, $ouuid, $envirronmentSource, $envirronmentTarget) {
		if($this->contentTypeService->getByName($type)->getEnvironment()->getName() == $envirronmentTarget){
			$this->session->getFlashBag()->add('warning', 'You can not align the default environment for '.$type.':'.$ouuid);
			return;
		}
		$contentType = $this->contentTypeService->getByName($type);
			
			
		if(! $this->authorizationChecker->isGranted($contentType->getPublishRole())) {
			$this->session->getFlashBag()->add('warning', 'You can not publish the content type  '.$contentType->getSingularName());
			return;
		}		
			
			
		$revision = $this->revRepository->findByOuuidAndContentTypeAndEnvironnement(
				$contentType,
				$ouuid, 
				$this->environmentService->getByName($envirronmentSource)
		);
	
		if(!$revision){
			$this->session->getFlashBag()->add('warning', 'Missing revision in the environment '.$envirronmentSource.' for '.$type.':'.$ouuid);
		}
		else{
			$target = $this->environmentService->getByName($envirronmentTarget);
			
			$toClean = $this->revRepository->findByOuuidAndContentTypeAndEnvironnement(
				$contentType,
				$ouuid,
				$target
			);
			
			if($toClean != $revision) {
				if($toClean) {
					$this->unpublish($toClean, $target);
				}
				$this->publish($revision, $target);
				
			}
			
		}
		
		
	}
	
	public function publish(Revision $revision, Environment $environment, $command=false) {
		if(!$command) {
	
			$user = $this->userService->getCurrentUser();
			if( !empty($environment->getCircles()) && !$this->authorizationChecker->isGranted('ROLE_ADMIN') && empty(array_intersect($environment->getCircles(), $user->getCircles()) )) {
				$this->session->getFlashBag()->add('warning', 'You are not allowed to publish in the environment '.$environment);
				return;
			}
			
			if(! $this->authorizationChecker->isGranted($revision->getContentType()->getPublishRole())) {
				$this->session->getFlashBag()->add('warning', 'You can not publish the content type  '.$contentType->getSingularName());
				return;
			}	
			
		}
		
		if($revision->getContentType()->getEnvironment() == $environment && !empty($revision->getEndTime())) {
			$this->session->getFlashBag()->add('warning', 'You can\'t publish in the default environment of the content type something else than the last revision: '.$revision);
			return;
		}

		$item = $this->revRepository->findByOuuidContentTypeAndEnvironnement($revision, $environment);
		
		$connection = $this->doctrine->getConnection();
		
		$already = false;
		if($item == $revision){
			$already = true;
			$this->session->getFlashBag()->add('notice', 'The revision '.$revision.' is already specified as published in '.$environment);
		}
		else if($item) {
			$statement = $connection->prepare("delete from environment_revision where environment_id = :envId and revision_id = :revId");
			$statement->bindValue('envId', $environment->getId());
			$statement->bindValue('revId', $item->getId());
			$statement->execute();		
		}

		$config =[
				'id' => $revision->getOuuid(),
				'index' => $environment->getAlias(),
				'type' => $revision->getContentType()->getName(),
				'body' => $this->dataService->sign($revision),
		];
		
		
		if($revision->getContentType()->getHavePipelines()){
			$config['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
		}
		
		$status = $this->client->index($config);
		
		if(!$already) {
			
			$connection = $this->doctrine->getConnection();
			$statement = $connection->prepare("insert into environment_revision (environment_id, revision_id) VALUES(:envId, :revId)");
			$statement->bindValue('envId', $environment->getId());
			$statement->bindValue('revId', $revision->getId());
			$statement->execute();
			if(!$command){
				$this->session->getFlashBag()->add('notice', 'Revision '.$revision.' has been published in '.$environment);				
			}

			$this->dispatcher->dispatch(RevisionPublishEvent::NAME,  new RevisionPublishEvent($revision, $environment));
		}

		if(!$command){
			$this->auditService->auditLog('PublishService:publish', $revision->getRawData(), $environment->getName());			
		}
		
		return $already?0:1;
		
	}
	
	public function unpublish(Revision $revision, Environment $environment, $command=false) {
		
		if(!$command){
	
			$user = $this->userService->getCurrentUser();
			if( !empty($environment->getCircles() && !$this->authorizationChecker->isGranted('ROLE_ADMIN') && empty(array_intersect($environment->getCircles(), $user->getCircles())) )) {
				$this->session->getFlashBag()->add('warning', 'You are not allowed to unpublish from the environment '.$environment);
				return;
			}
			
			
			if(! $this->authorizationChecker->isGranted($revision->getContentType()->getPublishRole())) {
				$this->session->getFlashBag()->add('warning', 'You can not unpublish the content type  '.$contentType->getSingularName());
				return;
			}	
			
		}
		
		if($revision->getContentType()->getEnvironment() == $environment) {
			$this->session->getFlashBag()->add('warning', 'You can\'t unpublish from the default environment of the content type '.$revision->getContentType());
			return;
		}
		

		$connection = $this->doctrine->getConnection();
		$statement = $connection->prepare("delete from environment_revision where environment_id = :envId and revision_id = :revId");
		$statement->bindValue('envId', $environment->getId());
		$statement->bindValue('revId', $revision->getId());
		$statement->execute();
		
		try {
			$status = $this->client->delete([
					'id' => $revision->getOuuid(),
					'index' => $environment->getAlias(),
					'type' => $revision->getContentType()->getName(),
			]);
			$this->session->getFlashBag()->add('notice', 'The object '.$revision.' has been unpublished from environment '.$environment->getName());

			$this->dispatcher->dispatch(RevisionUnpublishEvent::NAME,  new RevisionUnpublishEvent($revision, $environment));
		}
		catch(\Exception $e){
			if(!$revision->getDeleted()) {
				$this->session->getFlashBag()->add('warning', 'The object '.$revision.' was already unpublished from environment '.$environment->getName());
			}
		}
		if(!$command){
			$this->auditService->auditLog('PublishService:unpublish', $revision->getRawData(), $environment->getName());	
		}
	}
	
	
}