<?php

namespace EMS\CoreBundle\Service;


use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionNewDraftEvent;
use EMS\CoreBundle\Exception\DataStateException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Form\DataField\ComputedFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DataService
{
	
	protected $twig;
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
	/** @var RevisionRepository */
	protected $revRepository;
	/**@var Session $session*/
	protected $session;
	/**@var FormFactoryInterface $formFactory*/
	protected $formFactory;
	protected $container;
	protected $appTwig;
	/**@var FormRegistryInterface*/
	protected $formRegistry;
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
			FormFactoryInterface $formFactory,
			$container,
			FormRegistryInterface $formRegistry,
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
		$this->formFactory = $formFactory;
		$this->container = $container;
		$this->twig = $container->get('twig');
		$this->appTwig = $container->get('app.twig_extension');
		$this->formRegistry = $formRegistry;
		$this->dispatcher= $dispatcher;
	}
	
	
	public function unlockRevision(Revision $revision, $lockerUsername=null){
		if(empty($lockerUsername)){
			$lockerUsername = $this->tokenStorage->getToken()->getUsername();			
		}
		if($revision->getLockBy() === $lockerUsername && $revision->getLockUntil() > (new \DateTime())) { 
			$this->revRepository->unlockRevision($revision->getId());
		}
	}
	
	
	public function lockRevision(Revision $revision, $publishEnv=false, $super=false, $username=null){
		
		
		
		if(!empty($publishEnv) && !$this->authorizationChecker->isGranted('ROLE_PUBLISHER') ){
			throw new PrivilegeException($revision);
		}
		else if( !empty($publishEnv) && is_object($publishEnv) && !empty($publishEnv->getCircles()) && !$this->authorizationChecker->isGranted('ROLE_ADMIN') && !$this->appTwig->inMyCircles($publishEnv->getCircles()) ) {
			throw new PrivilegeException($revision);
		}
		else if(empty($publishEnv) && !empty($revision->getContentType()->getCirclesField()) && !empty($revision->getRawData()[$revision->getContentType()->getCirclesField()])) {
			if(!$this->appTwig->inMyCircles($revision->getRawData()[$revision->getContentType()->getCirclesField()])) {
				throw new PrivilegeException($revision);
			}
		}
		
		
		$em = $this->doctrine->getManager();
		if($username === NULL){
			$lockerUsername = $this->tokenStorage->getToken()->getUsername();
		} else {
			$lockerUsername = $username;
		}
		$now = new \DateTime();
		if($revision->getLockBy() != $lockerUsername && $now <  $revision->getLockUntil()) {
			throw new LockedException($revision);
		}
		
		if(!$username && !$this->container->get('app.twig_extension')->one_granted($revision->getContentType()->getFieldType()->getFieldsRoles(), $super)) {
			throw new PrivilegeException($revision);
		}
		//TODO: test circles
		
		
		$this->revRepository->lockRevision($revision->getId(), $lockerUsername, new \DateTime($this->lockTime));
		
		$revision->setLockBy($lockerUsername);
		if($username){
			//lock by a console script
			$revision->setLockUntil(new \DateTime("+10 seconds"));
		}
		else{
			$revision->setLockUntil(new \DateTime($this->lockTime));			
		}
		$revision->setStartTime($now);
		
		$em->flush();
	}
	
	public function getDataCircles(Revision $revision) {
		$out = [];
		if($revision->getContentType()->getCirclesField()) {
			$fieldValue = $revision->getRawData()[$revision->getContentType()->getCirclesField()];
			if(!empty($fieldValue)) {
				if(is_array($fieldValue)) {
					return $fieldValue;
				}
				else {
					$out[] = $fieldValue;
				}
			}
		}
		return $out;
	}
	
	/**
	 * 
	 * @param string $ouuid
	 * @param ContentType $contentType
	 * @param Environment $environment
	 * @return Revision
	 */
	public function getRevisionByEnvironment($ouuid, ContentType $contentType, Environment $environment) {
		return $this->revRepository->findByEnvironment($ouuid, $contentType, $environment);

	}
	
	public function propagateDataToComputedField(DataField $dataField, array $objectArray, $type, $ouuid){
		$found = false;
		if(null !== $dataField->getFieldType()){
			if(strcmp($dataField->getFieldType()->getType(),ComputedFieldType::class) == 0) {
				$template = $dataField->getFieldType()->getDisplayOptions()['valueTemplate'];
				if(empty($template)){
					$out = NULL;
				}
				else {
					try {
						$out = $this->twig->createTemplate($template)->render([
							'_source' => $objectArray,
							'_type' => $type,
							'_id' => $ouuid
						]);
						
						if($dataField->getFieldType()->getDisplayOptions()['json']){
							$out = json_decode($out);
						}
						
					}
					catch (\Exception $e) {
						$out = "Error in template: ".$e->getMessage();
					}					
				}
				$dataField->setRawData($out);
				$found = true;
			}
		}
		
		foreach ($dataField->getChildren() as $child){
			$found = $found || $this->propagateDataToComputedField($child, $objectArray, $type, $ouuid);
		}
		return $found;
	}
	
	public function convertInputValues(DataField $dataField) {
		foreach ($dataField->getChildren() as $child){
			$this->convertInputValues($child);
		}
		if(!empty($dataField->getFieldType()) && !empty($dataField->getFieldType()->getType())) {
				/**@var DataFieldType $dataFieldType*/
			$dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
			$dataFieldType->convertInput($dataField);
		}
	}
	
	public function generateInputValues(DataField $dataField) {
		
			foreach ($dataField->getChildren() as $child){
				$this->generateInputValues($child);
			}
			if(!empty($dataField->getFieldType()) && !empty($dataField->getFieldType()->getType())) {
				/**@var DataFieldType $dataFieldType*/
				$dataFieldType = $this->formRegistry->getType($dataField->getFieldType()->getType())->getInnerType();
				$dataFieldType->generateInput($dataField);			
			}
	}
	
	public function createData($ouuid, array $rawdata, ContentType $contentType, $byARealUser=true){

		$now = new \DateTime();
		$until = $now->add(new \DateInterval($byARealUser?"PT5M":"PT1M"));//+5 minutes
		$newRevision = new Revision();
		$newRevision->setContentType($contentType);
		$newRevision->setOuuid($ouuid);
		$newRevision->setStartTime($now);
		$newRevision->setEndTime(null);
		$newRevision->setDeleted(0);
		$newRevision->setDraft(1);
		if($byARealUser) {
			$newRevision->setLockBy($this->tokenStorage->getToken()->getUsername());			
		}
		else {
			$newRevision->setLockBy('DATA_SERVICE');
		}
		$newRevision->setLockUntil($until);
		$newRevision->setRawData($rawdata);
		
		$em = $this->doctrine->getManager();
		if(!empty($ouuid)){
			$revisionRepository = $em->getRepository('EMSCoreBundle:Revision');
			$anotherObject = $revisionRepository->findOneBy([
					'contentType' => $contentType,
					'ouuid' => $newRevision->getOuuid(),
					'endTime' => null
			]);
			
			if(!empty($anotherObject)) {
				throw new ConflictHttpException('Duplicate OUUID '.$ouuid.' for this content type');
			}			
		}
		
		$em->persist($newRevision);
		$em->flush();
		return $newRevision;
		
	}
	
	public function buildForm(Revision $revision){
		if( $revision->getDatafield() == NULL){
			$this->loadDataStructure($revision);
		}
			
		//Get the form from Factory
		$builder = $this->formFactory->createBuilder(RevisionType::class, $revision);
		$form = $builder->getForm();
		return $form;
	}
	
	public function finalizeDraft(Revision $revision, \Symfony\Component\Form\Form &$form=null, $username=null){
		if($revision->getDeleted()){
			throw new \Exception("Can not finalized a deleted revision");
		}
		if(null == $form && empty($username)) {
			if( $revision->getDatafield() == NULL){
				$this->loadDataStructure($revision);
			}
			
			//Get the form from Factory
			$builder = $this->formFactory->createBuilder(RevisionType::class, $revision);
			$form = $builder->getForm();
		}
		
		$this->lockRevision($revision, false, false, $username);
		
		
		$em = $this->doctrine->getManager();
	
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
	
		//TODO: test if draft and last version publish in
		
		if(!empty($revision->getAutoSave())){
			throw new DataStateException('An auto save is pending, it can not be finalized.');
		}
			
		$objectArray = $revision->getRawData();
		
		if($this->propagateDataToComputedField($revision->getDataField(), $objectArray, $revision->getContentType()->getName(), $revision->getOuuid())) {
			$objectArray = $this->mapping->dataFieldToArray($revision->getDataField());
			$revision->setRawData($objectArray);
		}
		
		//Validation
//    	if(!$form->isValid()){//Trying to work with validators
  		if(empty($form) || $this->isValid($form)){
		
			$config = [
				'index' => $revision->getContentType()->getEnvironment()->getAlias(),
				'type' => $revision->getContentType()->getName(),
				'body' => $objectArray,
			];
			
			if($revision->getContentType()->getHavePipelines()){
				$config['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
			}
			
			if(empty($revision->getOuuid())) {
				$status = $this->client->index($config);
				$revision->setOuuid($status['_id']);
			}
			else {
				$config['id'] = $revision->getOuuid();
				$status = $this->client->index($config);
		
				$item = $repository->findByOuuidContentTypeAndEnvironnement($revision);
				if($item){
					$this->lockRevision($item, false, false, $username);
					$item->removeEnvironment($revision->getContentType()->getEnvironment());
					$em->persist($item);
					$this->unlockRevision($item, $username);
				}
			}
				
			$revision->addEnvironment($revision->getContentType()->getEnvironment());
			$revision->getDataField()->propagateOuuid($revision->getOuuid());
			$revision->setDraft(false);
			
			$em->persist($revision);
			$em->flush();
			

			$this->unlockRevision($revision, $username);
			$this->dispatcher->dispatch(RevisionFinalizeDraftEvent::NAME,  new RevisionFinalizeDraftEvent($revision));
		
		} else {
 			$form->addError(new FormError("This Form is not valid!"));
			$this->session->getFlashBag()->add('error', 'The revision ' . $revision . ' can not be finalized');
		}
		return $revision;
	}
	

	public function getNewestRevision($type, $ouuid){
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
	
		/** @var ContentTypeRepository $contentTypeRepo */
		$contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
		$contentTypes = $contentTypeRepo->findBy([
				'name' => $type,
				'deleted' => false,
		]);
	
		if(count($contentTypes) != 1) {
			throw new NotFoundHttpException('Unknown content type');
		}
		$contentType = $contentTypes[0];
	
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
	
		/** @var Revision $revision */
		$revisions = $repository->findBy([
				'ouuid' => $ouuid,
				'endTime' => null,
				'contentType' => $contentType,
				'deleted' => false,
		]);
	
		if(count($revisions) == 1){
			if(null == $revisions[0]->getEndTime()){
				$revision = $revisions[0];
				return $revision;
			} else {
				throw new NotFoundHttpException('Revision for ouuid '.$ouuid.' and contenttype '.$type.' with end time '.$revisions[0]->getEndTime() );
			}
		} elseif(count($revisions) == 0){
			throw new NotFoundHttpException('Revision not found for ouuid '.$ouuid.' and contenttype '.$type);
		}  else {
			throw new Exception('Too much newest revisions available for ouuid '.$ouuid.' and contenttype '.$type);
		}
	}
	
	public function setMetaFields(Revision $revision) {
		$this->setCircles($revision);
		$this->setLabelField($revision);
	}
	
	public function setCircles(Revision $revision){
		$objectArray = $revision->getRawData();
		$circlesField = $revision->getContentType()->getCirclesField();
		if(!empty($circlesField) && 
				isset($objectArray[$circlesField])  && 
				!empty($objectArray[$circlesField]) ){
			if(is_array($objectArray[$circlesField])){
				$revision->setCircles($circlesField);
			} else {
				$revision->setCircles([$circlesField]);
				
			}
		}
		else {
			$revision->setCircles(null);
		}
	}
	
	public function setLabelField(Revision $revision){//setMetaField
		$objectArray = $revision->getRawData();
		$labelField = $revision->getContentType()->getLabelField();
		if(!empty($labelField) && 
				isset($objectArray[$labelField])  && 
				!empty($objectArray[$labelField]) ){
			$revision->setLabelField($objectArray[$labelField]);
		}
		else {
			$revision->setLabelField(null);
		}
	}
	
	public function initNewDraft($type, $ouuid, $fromRev = null, $username = NULL){
	
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
		
		/** @var ContentTypeRepository $contentTypeRepo */
		$contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
		/** @var ContentType $contentType */
		$contentType = $contentTypeRepo->findOneBy([
				'name' => $type,
				'deleted' => false,
		]);

		if(!$contentType){
			throw new NotFoundHttpException('ContentType '.$type.' Not found');
		}
		
		try{
			$revision = $this->getNewestRevision($type, $ouuid);
			$revision->setDeleted(false);
			if(null !== $revision->getDataField()) {
				$revision->getDataField()->propagateOuuid($revision->getOuuid());
			}
		}
		catch(NotFoundHttpException $e){
			$revision = new Revision();
			$revision->setDraft(true);
			$revision->setOuuid($ouuid);
			$revision->setContentType($contentType);
		}
		
		
		$this->setMetaFields($revision);
		
		$this->lockRevision($revision, false, false, $username);
		
		
	
		if(! $revision->getDraft()){
			$now = new \DateTime();
	
			if ($fromRev){
				$newDraft = new Revision($fromRev);
			} else {
				$newDraft = new Revision($revision);
			}
				
			$newDraft->setStartTime($now);
			$revision->setEndTime($now);
	
			$this->lockRevision($newDraft, false, false, $username);
	
			$em->persist($revision);
			$em->persist($newDraft);
			$em->flush();
			
			$this->dispatcher->dispatch(RevisionNewDraftEvent::NAME,  new RevisionNewDraftEvent($newDraft));
			
			return $newDraft;
		}
		return $revision;
	
	}

	public function discardDraft(Revision $revision){
		$this->lockRevision($revision);
	
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
	
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
	
		if(!$revision) {
			throw new NotFoundHttpException('Revision not found');
		}
		if(!$revision->getDraft() || null != $revision->getEndTime()) {
			throw new BadRequestHttpException('Only authorized on a draft');
		}
	
		$contentTypeId = $revision->getContentType()->getId();
	
		if(null != $revision->getOuuid()){
			/** @var QueryBuilder $qb */
			$qb = $repository->createQueryBuilder('t')
			->where('t.ouuid = :ouuid')
			->andWhere('t.id <> :id')
			->andWhere('t.deleted =  false')
			->andWhere('t.contentType =  :contentType')
			->orderBy('t.startTime', 'desc')
			->setParameter('ouuid', $revision->getOuuid())
			->setParameter('contentType', $revision->getContentType())
			->setParameter('id', $revision->getId())
			->setMaxResults(1);
			$query = $qb->getQuery();
	
	
			$result = $query->getResult();
			if(count($result) == 1){
				/** @var Revision $previous */
				$previous = $result[0];
				$this->lockRevision($previous);
				$previous->setEndTime(null);
				$em->persist($previous);
			}
	
		}
	
		$em->remove($revision);
	
		$em->flush();
	}
	
	public function delete($type, $ouuid){
	
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
		
		/** @var ContentTypeRepository $contentTypeRepo */
		$contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
		
		$contentTypes = $contentTypeRepo->findBy([
				'deleted' => false,
				'name' => $type,
		]);
		if(!$contentTypes || count($contentTypes) != 1) {
			throw new NotFoundHttpException('Content Type not found');
		}
		
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
		
		
		$revisions = $repository->findBy([
				'ouuid' => $ouuid,
				'contentType' => $contentTypes[0]
		]);
		
		/** @var Revision $revision */
		foreach ($revisions as $revision){
			$this->lockRevision($revision, true);
				
			/** @var Environment $environment */
			foreach ($revision->getEnvironments() as $environment){
				try{
					$this->client->delete([
							'index' => $environment->getAlias(),
							'type' => $revision->getContentType()->getName(),
							'id' => $revision->getOuuid(),
					]);
					$this->session->getFlashBag()->add('notice', 'The object has been unpublished from environment '.$environment->getName());
				}
				catch(Missing404Exception $e){
					if(!$revision->getDeleted()) {
						$this->session->getFlashBag()->add('warning', 'The object was already removed from environment '.$environment->getName());
					}
					throw $e;
				}
				$revision->removeEnvironment($environment);
			}
			$revision->setDeleted(true);
			$em->persist($revision);
		}
		$this->session->getFlashBag()->add('notice', 'The object have been marked as deleted! ');
		$em->flush();
	}
		
	public function loadDataStructure(Revision $revision){

		$data = new DataField();
		$data->setFieldType($revision->getContentType()->getFieldType());
		$data->setOrderKey($revision->getContentType()->getFieldType()->getOrderKey());
		$data->setRawData($revision->getRawData());
		$revision->setDataField($data);
		$revision->getDataField()->updateDataStructure($revision->getContentType()->getFieldType());
		$object = $revision->getRawData();
		$data->updateDataValue($object);
		if(count($object) > 0){
			$html = DataService::arrayToHtml($object);
			$this->session->getFlashBag()->add('warning', "Some data of this revision were not consumed by the content type:".$html);			
		}
	}
	
	public static function arrayToHtml(array $array){
		$out = '<ul>';
		foreach ($array as $id =>$item){
			$out .= '<li>'.$id.':';
			if(is_array($item)){
				$out .= DataService::arrayToHtml($item);
			}
			else {
				$out .= $item;
			}
			$out .= '</li>';
		}
		return $out.'</ul>';
	}
	
	public function isValid(\Symfony\Component\Form\Form &$form) {
		
		$viewData = $form->getViewData();
		
		//pour le champ hidden allFieldsAreThere de Revision
		if(!is_object($viewData) && 'allFieldsAreThere' == $form->getName()){
			return true;
		}
		
		if($viewData instanceof Revision) {
			/** @var DataField $dataField */
			$dataField = $viewData->getDatafield();
		} elseif($viewData instanceof DataField) {
			/** @var DataField $dataField */
			$dataField = $viewData;
		} else {
			throw new \Exception("Unforeseen type of viewData");
		}
		if($dataField->getFieldType() !== null && $dataField->getFieldType()->getType() !== null) {
			$dataFieldTypeClassName = $dataField->getFieldType()->getType();
	    	/** @var DataFieldType $dataFieldType */
	    	$dataFieldType = new $dataFieldTypeClassName();
		}
		$isValid = true;
		if(isset($dataFieldType) && $dataFieldType->isContainer()) {//If datafield is container or type is null => Container => Recursive
			$formChildren = $form->all();
			foreach ($formChildren as $child) {
				if($child instanceof \Symfony\Component\Form\Form) {
					$tempIsValid = $this->isValid($child);//Recursive
					$isValid = $isValid && $tempIsValid;
				}
			}
			if(!$isValid) {
				$form->addError(new FormError("At least one field is not valid!"));				
			}

		}
//   		$isValid = $isValid && $dataFieldType->isValid($dataField);
		if(isset($dataFieldType) && !$dataFieldType->isValid($dataField)) {
			$isValid = false;
			$form->addError(new FormError("This Field is not valid! ".$dataField->getMessages()[0]));
		}
    	
		return $isValid;
	}
	
	public function getRevisionById($id, ContentType $type){
	
		$em = $this->doctrine->getManager();
	
		/** @var ContentTypeRepository $contentTypeRepo */
		$contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
		$contentTypes = $contentTypeRepo->findBy([
				'name' => $type->getName(),
				'deleted' => false,
		]);
	
		if(count($contentTypes) != 1) {
			throw new NotFoundHttpException('Unknown content type');
		}
		$contentType = $contentTypes[0];
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
		/** @var Revision $revision */
		$revisions = $repository->findBy([
				'id' => $id,
				'endTime' => null,
				'contentType' => $contentType,
				'deleted' => false,
		]);
	
		if(count($revisions) == 1){
			if(null == $revisions[0]->getEndTime()){
				$revision = $revisions[0];
				return $revision;
			} else {
				throw new Exception('Revision for ouuid '.$id.' and contenttype '.$type.' with end time '.$revisions[0]->getEndTime() );
			}
		} elseif(count($revisions) == 0){
			throw new NotFoundHttpException('Revision not found for ouuid '.$id.' and contenttype '.$type);
		}  else {
			throw new Exception('Too much newest revisions available for ouuid '.$id.' and contenttype '.$type);
		}
		
	}
	
	public function replaceData(Revision $revision, array $rawData, $replaceOrMerge = "replace"){
		
		if(! $revision->getDraft()){
			$em = $this->doctrine->getManager();
			$this->lockRevision($revision, false, false);
			
			$now = new \DateTime();

			$newDraft = new Revision($revision);
			
			if ($replaceOrMerge === "replace") {
				$newDraft->setRawData($rawData);
			} elseif($replaceOrMerge === "merge") {
				$newRawData = array_merge($revision->getRawData(), $rawData);
				$newDraft->setRawData($newRawData);
			} else {
				$this->session->getFlashBag()->add('error', 'The revision ' . $revision . ' has not been replaced or replaced');
				return $revision;
			}
			
			$newDraft->setStartTime($now);
			$revision->setEndTime($now);
	
			$this->lockRevision($newDraft, false, false);
	
			$em->persist($revision);
			$em->persist($newDraft);
			$em->flush();
			return $newDraft;
		}else {
			$this->session->getFlashBag()->add('error', 'The revision ' . $revision . ' is not a finalize version');
		}
		return $revision;
	
	}
	

	public function waitForGreen(){
		$this->client->cluster()->health(['wait_for_status' => 'green']);
	}
}