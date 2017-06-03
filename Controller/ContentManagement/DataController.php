<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\HasNotCircleException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DataController extends AppController
{
	
	
	/**
	 * @Route("/data/{name}", name="data.root"))
	 */
	public function rootAction($name, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var ContentTypeRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:ContentType');
		$contentType = $repository->findOneBy([	
			'name' => $name,
			'deleted' => false
		]);
		
		if(!$contentType){
			throw NotFoundHttpException('Content type '.$name.' not found');
		}

		return $this->redirectToRoute('data.draft_in_progress', [
				'contentTypeId' => $contentType->getId(),
		]);
	}
	
	
	/**
	 * @Route("/data/draft/{contentTypeId}", name="data.draft_in_progress"))
	 */
	public function draftInProgressAction($contentTypeId, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var ContentTypeRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:ContentType');
		
		
		$contentType = $repository->find($contentTypeId);		
		
		if(!$contentType || count($contentType) != 1) {
			throw new NotFoundHttpException('Content type not found');
		}
		
		/** @var RevisionRepository $revisionRep */
		$revisionRep = $em->getRepository('EMSCoreBundle:Revision');
// 		$revisions = $revisionRep->findBy([
// 				'deleted' => false,
// 				'draft' => true,
// 				'endTime' => null,
// 				'contentType' => $contentTypeId
// 		]);
		
		$revisions= $revisionRep->findInProgresByContentType($contentType, $this->getUserService()->getCurrentUser()->getCircles(), $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'));
		
		
		return $this->render( 'EMSCoreBundle:data:draft-in-progress.html.twig', [
				'contentType' =>  $contentType,
				'revisions' => $revisions
		] );		
	}
	
	/**
	 * @Route("/data/view/{environmentName}/{type}/{ouuid}", name="data.view")
	 */
	public function viewDataAction($environmentName, $type, $ouuid, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var EnvironmentRepository $environmentRepo */
		$environmentRepo = $em->getRepository('EMSCoreBundle:Environment');
		$environments = $environmentRepo->findBy([
				'name' => $environmentName,
		]);
		if(!$environments || count($environments) != 1) {
			throw new NotFoundHttpException('Environment not found');
		}
		
		/** @var ContentTypeRepository $contentTypeRepo */
		$contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
		$contentTypes = $contentTypeRepo->findBy([
				'name' => $type,
				'deleted' => false,
		]);
		
		$contentType = null;
		if($contentTypes && count($contentTypes) == 1) {
			$contentType = $contentTypes[0];
		}
		
		try{
			/** @var Client $client */
			$client = $this->getElasticsearch();
			$result = $client->get([
					'index' => $environments[0]->getAlias(),
					'type' => $type,
					'id' => $ouuid,
			]);
		}
		catch(Missing404Exception $e){
			throw new NotFoundHttpException($type.' not found');			
		}
		
		return $this->render( 'EMSCoreBundle:data:view-data.html.twig', [
				'object' =>  $result,
				'environment' => $environments[0],
				'contentType' => $contentType,
		] );
	}
	
	/**
	 * @Route("/data/revisions-in-environment/{environment}/{type}:{ouuid}", name="data.revision_in_environment", defaults={"deleted":0})
 	 * @ParamConverter("contentType", options={"mapping": {"type" = "name", "deleted" = "deleted"}})
 	 * @ParamConverter("environment", options={"mapping": {"environment" = "name"}})
	 */
	public function revisionInEnvironmentDataAction(ContentType $contentType, $ouuid, Environment $environment, Request $request)
	{
		$revision = $this->getDataService()->getRevisionByEnvironment($ouuid, $contentType, $environment);
		if(!$revision){
			$this->addFlash('warning', 'No revision found in '.$environment->getName().' for '.$contentType->getName().':'.$ouuid);
			return $this->redirectToRoute('data.draft_in_progress', ['contentTypeId' => $contentType->getId()]);
		}
		return $this->redirectToRoute('data.revisions', [
				'type' => $contentType->getName(),
				'ouuid' => $ouuid,
				'revisionId' => $revision->getId(),
		]);
	}
	
	/**
	 * @Route("/data/revisions/{type}:{ouuid}/{revisionId}", defaults={"revisionId": false} , name="data.revisions")
	 */
	public function revisionsDataAction($type, $ouuid, $revisionId, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var ContentTypeRepository $contentTypeRepo */
		$contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
		
		$contentTypes = $contentTypeRepo->findBy([
				'deleted' => false,
				'name' => $type,
		]);
		if(!$contentTypes || count($contentTypes) != 1) {
			throw new NotFoundHttpException('Content Type not found');
		}
		/** @var ContentType $contentType */
		$contentType = $contentTypes[0];
		
		if(! $contentType->getEnvironment()->getManaged()){
			return $this->redirectToRoute('data.view', [
					'environmentName' => $contentType->getEnvironment()->getName(),
					'type' => $type,
					'ouuid' => $ouuid
			]);
		}
		
		
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
		
		/**@var Revision $revision */
		if(!$revisionId) {
			$revision = $repository->findOneBy([
					'endTime' => null,
					'ouuid' => $ouuid,
					'deleted' => false,
					'contentType' => $contentType,
			]);			
		}
		else {
			$revision = $repository->findOneById($revisionId);			
		}
		
	
		if(!$revision || $revision->getOuuid() != $ouuid || $revision->getContentType() != $contentType || $revision->getDeleted()) {
			throw new NotFoundHttpException('Revision not found');
		}
		
		$this->loadAutoSavedVersion($revision);
		
		$this->getDataService()->loadDataStructure($revision);
		
		$revision->getDataField()->orderChildren();
		
		
		$page = $request->query->get('page', 1);
		
		$revisionsSummary = $repository->getAllRevisionsSummary($ouuid, $contentTypes[0], $page);
		$lastPage = $repository->revisionsLastPage($ouuid, $contentTypes[0]);
		$counter = $repository->countRevisions($ouuid, $contentTypes[0]);
		$firstElemOfPage = $repository->firstElemOfPage($page);
		
		$availableEnv = $em->getRepository('EMSCoreBundle:Environment')->findAvailableEnvironements(
				$revision->getContentType()->getEnvironment());
		
		$objectArray = $this->get('ems.service.mapping')->dataFieldToArray ($revision->getDataField());
	
		

		/** @var Client $client */
		$client = $this->getElasticsearch();
		
		
		$searchForm  = new Search();
		$searchForm->setContentTypes($this->getContentTypeService()->getAllNames());
		$searchForm->setEnvironments($this->getContentTypeService()->getAllDefaultEnvironmentNames());
		$searchForm->setSortBy('_uid');
		$searchForm->setSortOrder('asc');
		
		$filter = $searchForm->getFilters()[0];
		$filter->setBooleanClause('must');
		$filter->setField($revision->getContentType()->getRefererFieldName());
		$filter->setPattern($type.':'.$ouuid);
		if(empty($revision->getContentType()->getRefererFieldName())){
		    $filter->setOperator('match_and');
		}
		else {
		    $filter->setOperator('term');
		}
		
// 		/**@var Form $form*/
// 		$form = $this->createForm ( SearchFormType::class, $searchForm, [
// 		    'method' => 'GET',
// 		] );
		
		$refParams = [ 
		    'type' => $searchForm->getContentTypes(),
			'index' => $this->getContentTypeService()->getAllAliases(),
			'size' => 100,
            'body'=> $this->getSearchService()->generateSearchBody($searchForm),
		];
		
		
		return $this->render( 'EMSCoreBundle:data:revisions-data.html.twig', [
				'revision' =>  $revision,
				'revisionsSummary' => $revisionsSummary,
				'availableEnv' => $availableEnv,
				'object' => $revision->getObject($objectArray),
				'referrers' => $client->search($refParams),
		        'referrersFilter' => $filter,
				'page' => $page,
				'lastPage' => $lastPage,
				'counter' => $counter,
				'firstElemOfPage' => $firstElemOfPage,
		] );
	}
	
	public function getNewestRevision($type, $ouuid){
		return $this->getDataService()->getNewestRevision($type, $ouuid);
	}
	
	/**
	 * 
	 * @param unknown $type
	 * @param unknown $ouuid
	 * @param unknown $fromRev
	 * @return Revision
	 */
	public function initNewDraft($type, $ouuid, $fromRev = null){
		return $this->getDataService()->initNewDraft($type, $ouuid, $fromRev);
	}
	
	
	/**
	 *
	 * @Route("/data/copy/{environment}/{type}/{ouuid}", name="revision.copy"))
	 * @Method({"GET"})
	 */
	public function copyAction($environment, $type, $ouuid, Request $request)
	{
		$envObj = $this->getEnvironmentService()->getByName($environment);
		$dataRaw = $this->getElasticsearch()->get([
				'index' => $envObj->getAlias(),
				'id' => $ouuid,
				'type' => $type,
		]);
		
		$this->addFlash('notice', 'The data of this object has been copied');
		$request->getSession()->set('ems_clipboard', $dataRaw['_source']);
		
		return $this->redirectToRoute('data.view', [
				'environmentName' => $environment, 
				'type' => $type, 
				'ouuid' => $ouuid,
		]);
	}
	
	
	/**
	 *
	 * @Route("/data/new-draft/{type}/{ouuid}", name="revision.new-draft"))
	 * @Method({"POST"})
	 */
	public function newDraftAction($type, $ouuid, Request $request)
	{
		return $this->redirectToRoute('revision.edit', [
				'revisionId' => $this->initNewDraft($type, $ouuid)->getId()
		]);
	}
	
	
	/**
	 * 
	 * @Route("/data/delete/{type}/{ouuid}", name="object.delete"))
     * @Method({"POST"})
	 */
	public function deleteAction($type, $ouuid, Request $request)
	{
	    $revision = $this->getDataService()->getNewestRevision($type, $ouuid);
	    $contentType = $revision->getContentType();
	    $found = false;
	    foreach ($this->getEnvironmentService()->getAll() as $environment) {
	        /**@var Environment $environment*/
	        if($environment !== $revision->getContentType()->getEnvironment()){
	            try{
	                $sibling = $this->getDataService()->getRevisionByEnvironment($ouuid, $revision->getContentType(), $environment);
	                if($sibling){
	                    $this->addFlash('warning', 'A revision as been found in '.$environment->getName().'. Consider to unpublish it first.');
	                    $found = true;
	                }
	            }
	            catch (NoResultException $e){
	                
	            }
	        }
	    }
	    
	    if($found){
	        return $this->redirectToRoute('data.revisions', [
	            'type' => $type,
	            'ouuid' => $ouuid,
	        ]);
	    }
	    
	    $this->getDataService()->delete($type, $ouuid);
	    
	    return $this->redirectToRoute('elasticsearch.search', [
	        'search_form[contentTypes][0]' => $contentType->getName(),
	        'search_form[environments][0]' => $contentType->getEnvironment()->getName(),
	    ]);
	}
	
	public function discardDraft(Revision $revision){
		return $this->getDataService()->discardDraft($revision);
	}
	
	/**
	 * 
	 * @Route("/data/draft/discard/{revisionId}", name="revision.discard"))
     * @Method({"POST"})
	 */
	public function discardRevisionAction($revisionId, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
		/** @var Revision $revision */
		$revision = $repository->find($revisionId);
		
		if(!$revision) {
			throw $this->createNotFoundException('Revision not found');
		}
		if(!$revision->getDraft() || null != $revision->getEndTime()) {
			throw BadRequestHttpException('Only authorized on a draft');
		}


		$contentTypeId = $revision->getContentType()->getId();
		$type = $revision->getContentType()->getName();
		$ouuid = $revision->getOuuid();
		
		$this->discardDraft($revision);
		
		if(null != $ouuid){
			return $this->redirectToRoute('data.revisions', [
					'type' => $type,
					'ouuid'=> $ouuid,
			]);
		}
		return $this->redirectToRoute('data.draft_in_progress', [
				'contentTypeId' => $contentTypeId
		]);			
	}
	
	
	/**
	 * 
	 * @Route("/data/cancel/{revision}", name="revision.cancel"))
     * @Method({"POST"})
	 */
	public function cancelModificationsAction(Revision $revision, Request $request)
	{
		$contentTypeId = $revision->getContentType()->getId();
		$type = $revision->getContentType()->getName();
		$ouuid = $revision->getOuuid();
		

		$this->lockRevision($revision);
		
		$em = $this->getDoctrine()->getManager();
		$revision->setAutoSave(null);
		$em->persist($revision);
		$em->flush();
		
		if(null != $ouuid){
			return $this->redirectToRoute('data.revisions', [
					'type' => $type,
					'ouuid'=> $ouuid,
			]);
		}
		return $this->redirectToRoute('data.draft_in_progress', [
				'contentTypeId' => $contentTypeId
		]);			
	}
	
	
	/**
	 * @Route("/data/revision/re-index/{revisionId}", name="revision.reindex"))
     * @Method({"POST"})
	 */
	public function reindexRevisionAction($revisionId, Request $request){
		
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
		/** @var Revision $revision */
		$revision = $repository->find($revisionId);
		
		if(!$revision) {
			throw $this->createNotFoundException('Revision not found');
		}
		
		$this->lockRevision($revision);
		
		/** @var Client $client */
		$client = $this->getElasticsearch();
		
	
		try{

			$this->getDataService()->loadDataStructure($revision);
			
			$objectArray = $this->get('ems.service.mapping')->dataFieldToArray ($revision->getDataField());
			/** @var \EMS\CoreBundle\Entity\Environment $environment */
			foreach ($revision->getEnvironments() as $environment ){
				$status = $client->index([
						'id' => $revision->getOuuid(),
						'index' => $this->getParameter('ems_core.instance_id').$environment->getName(),
						'type' => $revision->getContentType()->getName(),
						'body' => $objectArray
				]);				

				$this->addFlash('notice', 'Reindexed in '.$environment->getName());
			}
		}
		catch (\Exception $e){
			$this->addFlash('warning', 'Reindexing has failed: '.$e->getMessage());
		}
		return $this->redirectToRoute('data.revisions', [
				'ouuid' => $revision->getOuuid(),
				'type' => $revision->getContentType()->getName(),
				'revisionId' => $revision->getId(),
		]);
		
	}

	/**
	 * @Route("/data/custom-index-view/{viewId}", name="data.customindexview"))
	 */
	public function customIndexViewAction($viewId, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		/** @var ViewRepository $viewRepository */
		$viewRepository = $em->getRepository('EMSCoreBundle:View');
		
		$view = $viewRepository->find($viewId);
		/** @var View $view **/
			
		if(!$view) {
			throw new NotFoundHttpException('View type not found');
		}
		
		/** @var \EMS\CoreBundle\Form\View\ViewType $viewType */
 		$viewType = $this->get($view->getType());
		
 		return $viewType->generateResponse($view, $request);		
	}

	/**
	 * @Route("/data/custom-view/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download": false} , name="data.customview"))
	 */
	public function customViewAction($environmentName, $templateId, $ouuid, Request $request, $_download)
	{	
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var TemplateRepository $templateRepository */
		$templateRepository = $em->getRepository('EMSCoreBundle:Template');
		
		/** @var Template $template **/
		$template = $templateRepository->find($templateId);
			
		if(!$template) {
			throw new NotFoundHttpException('Template type not found');
		}
		
		/** @var EnvironmentRepository $environmentRepository */
		$environmentRepository = $em->getRepository('EMSCoreBundle:Environment');
		
		/** @var Environment $environment **/
		$environment = $environmentRepository->findBy([
			'name' => $environmentName,
		]);
			
		if(!$environment || count($environment) != 1) {
			throw new NotFoundHttpException('Environment type not found');
		}
		
		$environment = $environment[0];
		
		/** @var Client $client */
		$client = $this->getElasticsearch();
		
		$object = $client->get([
				'index' => $environment->getAlias(),
				'type' => $template->getContentType()->getName(),
				'id' => $ouuid
		]);
		
		$twig = $this->getTwig();
		
		try {
			//TODO why is the body generated and passed to the twig file while the twig file does not use it?
			//Asked by dame
			//If there is an error in the twig the user will get an 500 error page, this solution is not perfect but at least the template is tested
			$body = $twig->createTemplate($template->getBody());
		}
		catch (\Twig_Error $e){
			$this->addFlash('error', 'There is something wrong with the template body field '.$template->getName());
			$body = $twig->createTemplate('error in the template!');
		}
		
		if($_download || (strcmp($template->getRenderOption(), "export") === 0 && ! $template->getPreview()) ){
			if(null!= $template->getMimeType()){
				header('Content-Type: '.$template->getMimeType());		
			}
			
			$filename = $ouuid;
			if (null != $template->getFilename()){
				try {
					$filename = $twig->createTemplate($template->getFilename());
				} catch (\Twig_Error $e) {
					$this->addFlash('error', 'There is something wrong with the template filename field '.$template->getName());
					$filename = $twig->createTemplate('error in the template!');
				}
				
				$filename = $filename->render([
						'environment' => $environment,
						'contentType' => $template->getContentType(),
						'object' => $object,
						'source' => $object['_source'],
				]);
				$filename = preg_replace('~[\r\n]+~', '', $filename);
			}
			
			if(null!= $template->getExtension()){
				header("Content-Disposition: attachment; filename=".$filename.'.'.$template->getExtension());
			}
			
			$output = $body->render([
						'environment' => $environment,
						'contentType' => $template->getContentType(),
						'object' => $object,
						'source' => $object['_source'],
				]);
			echo $output;
			
			exit;
		}
		
		return $this->render( 'EMSCoreBundle:data:custom-view.html.twig', [
				'template' =>  $template,
				'object' => $object,
				'environment' => $environment,
				'contentType' => $template->getContentType(),
				'body' => $body
		] );
		
	}
	
	private function loadAutoSavedVersion(Revision $revision){
		if(null != $revision->getAutoSave()){
			$revision->setRawData($revision->getAutoSave());
			$this->addFlash('warning', "Data were loaded from an autosave version by ".$revision->getAutoSaveBy()." at ".$revision->getAutoSaveAt()->format($this->getParameter('ems_core.date_time_format')));			
		}
	}


	/**
	 * @Route("/data/revision/{revisionId}.json", name="revision.ajaxupdate"))
     * @Method({"POST"})
	 */
	public function ajaxUpdateAction($revisionId, Request $request)
	{
		
		$em = $this->getDoctrine()->getManager();
		
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
		/** @var Revision $revision */
		$revision = $repository->find($revisionId);
		
		
		if(!empty($revision->getFinalizedBy())) {
			//HAs been alredy finalized so, autosave is disabled
			return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
					'success' => false,
			] );
		}
		
		if( empty($request->request->get('revision')) || empty($request->request->get('revision')['allFieldsAreThere']) || !$request->request->get('revision')['allFieldsAreThere']) {
			$this->addFlash('error', 'Incomplete request, some fields of the request are missing, please verifiy your server configuration. (i.e.: max_input_vars in php.ini)');
			$this->addFlash('error', 'Your modification are not saved!');
		}

		$this->lockRevision($revision);
		
		if(!$revision) {
			throw new NotFoundHttpException('Unknown revision');
		}		

		$this->getDataService()->loadDataStructure($revision);
		
		$form = $this->createForm(RevisionType::class, $revision);
		
		
		/**little trick to reorder collection*/
		$requestRevision = $request->request->get('revision');
		$this->reorderCollection($requestRevision);
		$request->request->set('revision', $requestRevision);
		/**end little trick to reorder collection*/
		
		$form->handleRequest($request);
				
		/** @var Revision $revision */
		$revision = $form->getData();

		$this->getDataService()->convertInputValues($revision->getDataField());
		
		$objectArray = $this->get('ems.service.mapping')->dataFieldToArray($revision->getDataField());
		$revision->setAutoSave($objectArray);
		$revision->setAutoSaveAt(new \DateTime());
		$revision->setAutoSaveBy($this->getUser()->getUsername());
		
		$em->persist($revision);
		$em->flush();			

		$this->getDataService()->isValid($form);
		$formErrors = $form->getErrors(true, true);
			
		return $this->render( 'EMSCoreBundle:data:ajax-revision.json.twig', [
// 				'revision' =>  $revision,
				'success' => true,
				'formErrors' => $formErrors,
		] );
	}
	
	
	/**
	 * @Route("/data/draft/finalize/{revision}", name="revision.finalize"))
	 */
	public function finalizeDraftAction(Revision $revision){

		
		$this->getDataService()->loadDataStructure($revision);
		try{
			$form = $this->createForm(RevisionType::class, $revision);
			if(!empty($revision->getAutoSave())){
				$this->addFlash("error", "This draft (".$revision->getContentType()->getSingularName().($revision->getOuuid()?":".$revision->getOuuid():"").") can't be finlized, as an autosave is pending.");
				return $this->render( 'EMSCoreBundle:data:edit-revision.html.twig', [
						'revision' =>  $revision,
						'form' => $form->createView(),
				] );
			}
			
			$revision = $this->getDataService()->finalizeDraft($revision, $form);
			if(count($form->getErrors()) !== 0) {
				$this->addFlash("error", "This draft (".$revision->getContentType()->getSingularName().($revision->getOuuid()?":".$revision->getOuuid():"").") can't be finlized.");
				return $this->render( 'EMSCoreBundle:data:edit-revision.html.twig', [
						'revision' =>  $revision,
						'form' => $form->createView(),
				] );
			}
				
		}
		catch (\Exception $e){
			$this->addFlash("error", "This draft (".$revision->getContentType()->getSingularName().($revision->getOuuid()?":".$revision->getOuuid():"").") can't be finlized.");
			$this->addFlash('error', $e->getMessage());
			return $this->redirectToRoute('revision.edit', [
					'revisionId' => $revision->getId(),
			]);
		}
		
		return $this->redirectToRoute('data.revisions', [
				'ouuid' => $revision->getOuuid(),
				'type' => $revision->getContentType()->getName(),
				'revisionId' => $revision->getId(),
		]);
	}
	
	public function finalizeDraft(Revision $revision, \Symfony\Component\Form\Form $form=null, $username=null){
//		TODO: User validators
// 		$validator = $this->get('validator');
// 		$errors = $validator->validate($revision);
		
		return $this->getDataService()->finalizeDraft($revision, $form, $username);
	}
	
	private function reorderCollection(&$input){
		if(is_array($input) && !empty($input)){
			if(is_int(array_keys($input)[0])){
				$input = array_values($input);
			}
			foreach($input as &$elem){
				$this->reorderCollection($elem);
			}
		}
	}
	
	/**
	 * @Route("/data/draft/edit/{revisionId}", name="revision.edit"))
	 */
	public function editRevisionAction($revisionId, Request $request)
	{
		$em = $this->getDoctrine()->getManager();
		$logger = $this->get('logger');
		
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Revision');
		/** @var Revision $revision */
		$revision = $repository->find($revisionId);
		
		if(!$revision) {
			throw new NotFoundHttpException('Unknown revision');
		}

		$this->lockRevision($revision);
		$logger->debug('Revision '.$revisionId.' locked');
		
		//TODO:Only a super user can edit a archived revision
		
		if ( $request->isMethod('GET') ) {
			$this->loadAutoSavedVersion($revision);
		}
		
		$this->getDataService()->loadDataStructure($revision);
		$this->getDataService()->generateInputValues($revision->getDataField());

		$logger->debug('DataField structure generated');
		
		$form = $this->createForm(RevisionType::class, $revision, [
				'has_clipboard' => $request->getSession()->has('ems_clipboard'),
				'has_copy' => $this->getAuthorizationChecker()->isGranted('ROLE_ADMIN'),
		]);

		$logger->debug('Revision\'s form created');
		

		
		/**little trick to reorder collection*/
		$requestRevision = $request->request->get('revision');
		$this->reorderCollection($requestRevision);
		$request->request->set('revision', $requestRevision);
		/**end little trick to reorder collection*/
		
		
		$form->handleRequest($request);

		$logger->debug('Revision request form handled');
		
		
		if ($form->isSubmitted()) {//Save, Finalize or Discard
			
			if( empty($request->request->get('revision')) || empty($request->request->get('revision')['allFieldsAreThere']) || !$request->request->get('revision')['allFieldsAreThere']) {
				$this->addFlash('error', 'Incomplete request, some fields of the request are missing, please verifiy your server configuration. (i.e.: max_input_vars in php.ini)');
				return $this->redirectToRoute('data.revisions', [
						'ouuid' => $revision->getOuuid(),
						'type' => $revision->getContentType()->getName(),
						'revisionId' => $revision->getId(),
				]);
			}
			
			
			
			$revision->setAutoSave(null);
			if(!array_key_exists('discard', $request->request->get('revision'))) {//Save, Copy, Paste or Finalize
				
				//Save anyway
				/** @var Revision $revision */
				$revision = $form->getData();
				$this->get('logger')->debug('Revision extracted from the form');
				
				$this->getDataService()->convertInputValues($revision->getDataField());
				
				$objectArray = $this->get('ems.service.mapping')->dataFieldToArray($revision->getDataField());
				
				
				if(array_key_exists('paste', $request->request->get('revision'))) {//Paste
					$this->addFlash('notice', 'Data have been paste');
					$objectArray = array_merge($objectArray, $request->getSession()->get('ems_clipboard', []));
					$this->get('logger')->debug('Paste data have benn merged');
				}
				
				if(array_key_exists('copy', $request->request->get('revision'))) {//Copy
					$request->getSession()->set('ems_clipboard', $objectArray);
					$this->addFlash('notice', 'Data have been copied');
				}
				
				$revision->setRawData($objectArray);
				
				
				$this->getDataService()->setMetaFields($revision);
				
				$logger->debug('Revision before persist');
				$em->persist($revision);
				$em->flush();
	
				$logger->debug('Revision after persist flush');
				
				if(array_key_exists('publish', $request->request->get('revision'))) {//Finalize
					
					try{
						$revision = $this->finalizeDraft($revision, $form);
						if(count($form->getErrors()) === 0) {
							return $this->redirectToRoute('data.revisions', [
									'ouuid' => $revision->getOuuid(),
									'type' => $revision->getContentType()->getName(),
							]);
						} else {
							$this->addFlash("warning", "This draft (".$revision->getContentType()->getSingularName().($revision->getOuuid()?":".$revision->getOuuid():"").") can't be finlized.");
							return $this->render( 'EMSCoreBundle:data:edit-revision.html.twig', [
									'revision' =>  $revision,
									'form' => $form->createView(),
							] );
						}
					}
					catch (\Exception $e){
						$this->addFlash('error', 'The draft has been saved but something when wrong when we tried to publish it. '.$revision->getContentType()->getName().':'.$revision->getOuuid());
						$this->addFlash('error', $e->getMessage());
						return $this->redirectToRoute('revision.edit', [
								'revisionId' => $revisionId,
						]);	
					}
					
				}
			}
			
			//if paste or copy
			if(array_key_exists('paste', $request->request->get('revision')) || array_key_exists('copy', $request->request->get('revision')) ) {//Paste or copy
				return $this->redirectToRoute('revision.edit', [
						'revisionId' => $revisionId,
				]);
			}
			//if Save or Discard
			if(null != $revision->getOuuid()){
				return $this->redirectToRoute('data.revisions', [
						'ouuid' => $revision->getOuuid(),
						'type' => $revision->getContentType()->getName(),
						'revisionId' => $revision->getId(),
				]);
			}
			else{
				return $this->redirectToRoute('data.draft_in_progress', [
						'contentTypeId' => $revision->getContentType()->getId(),
				]);
			}
				
		}
		else{
			$isValid = $this->getDataService()->isValid($form);
			if ( !$isValid ) {
				$this->addFlash("warning", "This draft (".$revision->getContentType()->getSingularName().($revision->getOuuid()?":".$revision->getOuuid():"").") can't be finlized.");
			}
		}
		// Call Audit service for log
		$this->get("ems.service.audit")->auditLog('DataController:editRevision', $revision->getRawData());
		$logger->debug('Start twig rendering');
		return $this->render( 'EMSCoreBundle:data:edit-revision.html.twig', [
				'revision' =>  $revision,
				'form' => $form->createView(),
		] );		
	}
		
	/**
	 * deprecated should removed
	 * 
	 * @param Revision $revision
	 * @param string $publishEnv
	 * @param string $super
	 */
	private function lockRevision(Revision $revision, $publishEnv=false, $super=false){
		$this->getDataService()->lockRevision($revision, $publishEnv, $super);
	}
	
	
	/**
 	 * @throws HasNotCircleException
	 * @Route("/data/add/{contentType}", name="data.add"))
	 */
	public function addAction(ContentType $contentType, Request $request)
	{
		$userCircles = $this->getUser()->getCircles();
		$environment = $contentType->getEnvironment();
		$environmentCircles = $environment->getCircles();
		if(!empty($environmentCircles)){
			if (empty($userCircles)){
				throw new HasNotCircleException($environment);
			}
			$found = false;
			foreach($userCircles as $userCircle){
				if(in_array($userCircle,$environmentCircles))
				{
					$found = true;
					break;
				}
			}
			if(!$found){
				throw new HasNotCircleException($environment);
			}
		}
		
		$em = $this->getDoctrine()->getManager();
		
		$revision = new Revision();
		
		$form = $this->createFormBuilder($revision)
			->add('ouuid', IconTextType::class, [
					'attr' => [
						'class' => 'form-control',
						'placeholder' => 'Auto-generated if left empty'
					],
					'required' => false
			])
			->add('save', SubmitType::class, [
					'label' => 'Create '.$contentType->getName().' draft',
					'attr' => [
						'class' => 'btn btn-primary pull-right'
					]
			])
			->getForm();
			
		$form->handleRequest($request);
			
		
		if (($form->isSubmitted() && $form->isValid()) || ! $contentType->getAskForOuuid()) {
			/** @var Revision $revision */
			$revision = $form->getData();
			
			if( !$this->get('security.authorization_checker')->isGranted($contentType->getCreateRole()) ){
				throw new PrivilegeException($revision);
			}
			

			if(null != $revision->getOuuid()){
				$revisionRepository = $em->getRepository('EMSCoreBundle:Revision');
				$anotherObject = $revisionRepository->findBy([
						'contentType' => $contentType,
						'ouuid' => $revision->getOuuid(),
						'endTime' => null
				]);
				
				if( count($anotherObject) != 0 ){
					$form->get('ouuid')->addError(new FormError('Another '.$contentType->getName().' with this identifier already exists'));
// 					$form->addError(new FormError('Another '.$contentType->getName().' with this identifier already exists'));					
				}
			}			
			
			if($form->isValid() || ! $contentType->getAskForOuuid()) {
				$now = new \DateTime('now');
				$revision->setContentType($contentType);
				$revision->setDraft(true);
				$revision->setDeleted(false);
				$revision->setStartTime($now);
				$revision->setEndTime(null);
				$revision->setLockBy( $this->getUser()->getUsername() );
				$revision->setLockUntil(new \DateTime($this->getParameter('ems_core.lock_time')));
				
				if($contentType->getCirclesField()) {
					$fieldType = $contentType->getFieldType()->getChildByPath($contentType->getCirclesField());
					if($fieldType) {
						/**@var \EMS\CoreBundle\Entity\User $user*/
						$user = $this->getUser();
						$options = $fieldType->getDisplayOptions();
						if(isset($options['multiple']) && $options['multiple']){
							//merge all my circles with the default value
							$circles = [];
							if(isset($options['defaultValue'])){
								$circles = json_decode($options['defaultValue']);
								if(!is_array($circles)){
									$circles = [$circles];
								}
							}
							$circles = array_merge($circles, $user->getCircles());
							$revision->setRawData([$contentType->getCirclesField() => $circles]);
							$revision->setCircles($circles);
							
						}
						else{
							//set first of my circles
							if(!empty($user->getCircles())){
								$revision->setRawData([$contentType->getCirclesField() => $user->getCircles()[0]]);
								$revision->setCircles([$user->getCircles()[0]]);
							}
						}
					}
				}
				$this->getDataService()->setMetaFields($revision);
				
				$em->persist($revision);
				$em->flush();
				
				return $this->redirectToRoute('revision.edit', [
						'revisionId' => $revision->getId()
				]);				
			}
		}
		
		return $this->render( 'EMSCoreBundle:data:add.html.twig', [
				'contentType' =>  $contentType,
				'form' => $form->createView(),
		] );	
	}
	
	/**
	 * @Route("/data/revisions/revert/{id}", name="revision.revert"))
	 * @Method({"POST"})
	 */
	public function revertRevisionAction(Revision $revision, Request $request)
	{
		$type = $revision->getContentType()->getName();
		$ouuid = $revision->getOuuid();
		
		$newestRevision = $this->getNewestRevision($type, $ouuid);
		if ($newestRevision->getDraft()){
			//TODO: ????
		}
		
		$revertedRevsision = $this->initNewDraft($type, $ouuid, $revision);
		$this->addFlash('notice', 'Revision '.$revision->getId().' reverted as draft');
		
		return $this->redirectToRoute('revision.edit', [
				'revisionId' => $revertedRevsision->getId()
		]);
	}
	
	/**
	 * @Route("/data/link/{key}", name="data.link")
	 */
	public function linkDataAction($key, Request $request)
	{	
		$splitted = explode(':', $key);		
		if($splitted && count($splitted) == 3){
			$category 	= $splitted[0]; // object or asset
			$type 		= $splitted[1];
			$ouuid 		= $splitted[2];
		}
		
		if(null != $ouuid && null != $type) {
			/** @var EntityManager $em */
			$em = $this->getDoctrine ()->getManager ();
			
			/** @var RevisionRepository $repository */
			$repository = $em->getRepository ( 'EMSCoreBundle:Revision' );
			
			/**@var ContentTypeService $ctService*/
			$ctService = $this->getContentTypeService();
			
			
			$contentType = $ctService->getByName($type);

			if(empty($contentType)){
				throw new NotFoundHttpException('Content type '.$type.'not found' );				
			}

			/**@var Revision $revision */
			$revision = $repository->findByOuuidAndContentTypeAndEnvironnement($contentType, $ouuid, $contentType->getEnvironment());
			
			if(!$revision){
				throw new NotFoundHttpException('Impossible to find this item : ' . $ouuid);
			}
						
			
			
			
			// For each type, we must perform a different redirect.
			if($category == 'object'){
				return $this->redirectToRoute('data.revisions', [
						'type' => $type,
						'ouuid'=> $ouuid,
				]);
			} else if ($category == 'asset') { 
								
				if(empty($contentType->getAssetField()) && empty($revision->getRawData()[$contentType->getAssetField()])) {
					throw new NotFoundHttpException('Asset field not found for '. $revision);
				}
				return $this->redirectToRoute('file.download', [
						'sha1' => $revision->getRawData()[$contentType->getAssetField()]['sha1'],
						'type' => $revision->getRawData()[$contentType->getAssetField()]['mimetype'],
						'name' => $revision->getRawData()[$contentType->getAssetField()]['filename'],
				]);
				
			}
		} else {
			throw new NotFoundHttpException('Impossible to find this item : ' . $key);
		}
	}
}