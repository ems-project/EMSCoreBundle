<?php

namespace Ems\CoreBundle\Controller\ContentManagement;

use Ems\CoreBundle;
use Ems\CoreBundle\Controller\AppController;
use Ems\CoreBundle\Entity\ContentType;
use Ems\CoreBundle\Service\DataService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CrudController extends AppController
{
	/**
	 * 
	 * @return DataService
	 */
	private function dataService() {
		return $this->get('ems.service.data');
	}
	
	
	/**
	 * @Route("/api/{name}/create/{ouuid}", defaults={"ouuid": null, "_format": "json"})
	 * @Route("/api/{name}/draft/{ouuid}", defaults={"ouuid": null, "_format": "json"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     * @Method({"POST"})
	 */
	public function createAction($ouuid, ContentType $contentType, Request $request) {
		
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not create content for a managed content type');	
		}
		
		$rawdata = json_decode($request->getContent(), true);
		if (empty($rawdata)){
			throw new BadRequestHttpException('Not a valid JSON message');	
		}
		
		try {
			$newRevision = $this->dataService()->createData($ouuid, $rawdata, $contentType);
		    $isCreated = (isset($newRevision)) ?  true : false;
		    
		} catch (\Exception $e) {
			
			$this->addFlash('error', 'The revision ' . $revision . ' can not be created');
			$isCreated = false;
		}
		
		return $this->render( 'ajax/notification.json.twig', [
				'success' => $isCreated,
				'revision_id' => $newRevision->getId(),
		]);
	}
	
	
	/**
	 * @Route("/api/{name}/finalize/{id}", defaults={"_format": "json"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     * @Method({"GET"})
	 */
	public function finalizeAction($id, ContentType $contentType, Request $request) {
		
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not create content for a managed content type');	
		}
		
		try {
			$revision = $this->dataService()->getRevisionById($id, $contentType);
			$newRevision = $this->dataService()->finalizeDraft($revision);
			$isFinalize = !$newRevision->getDraft();
			
		} catch (\Exception $e) {
			if (($e instanceof NotFoundHttpException) OR ($e instanceof DataStateException)) {
				$this->addFlash('error', $e->getMessage());
			} else {
				$this->addFlash('error', 'The revision ' . $id . ' can not be finalized');
			}
			$isFinalize = false;
			
		}
		return $this->render( 'ajax/notification.json.twig', [
				'success' => $isFinalize,
		]);
	}
	
	/**
	 * @Route("/api/{name}/discard/{id}", defaults={"_format": "json"})
	 * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
	 * @Method({"GET"})
	 */
	public function discardAction($id, ContentType $contentType, Request $request) {
	
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not create content for a managed content type');
		}
	
		try {
			$revision = $this->dataService()->getRevisionById($id, $contentType);
			$this->dataService()->discardDraft($revision);
			$isDiscard = ($revision->getId() != $id) ? true : false;

		} catch (\Exception $e) {
			if (($e instanceof NotFoundHttpException) OR ($e instanceof BadRequestHttpException)) {
				 $this->addFlash('error', $e->getMessage());
			} else {
				 $this->addFlash('error', 'The revision ' . $id . ' can not be dicscarded');
			}
			$isDiscard = false;
				
		}
		return $this->render( 'ajax/notification.json.twig', [
				'success' => $isDiscard,
		]);
	}
	
	/**
	 * @Route("/api/{name}/replace/{ouuid}", defaults={"_format": "json"})
	 * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
	 * @Method({"POST"})
	 */
	public function replaceAction($ouuid, ContentType $contentType, Request $request) {
	
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not create content for a managed content type');	
		}
		
		$rawdata = json_decode($request->getContent(), true);
		if (empty($rawdata)){
			throw new BadRequestHttpException('Not a valid JSON message');	
		}
		
		try {
			$revision = $this->dataService()->getNewestRevision($contentType->getName(), $ouuid);
			$newDraft = $this->dataService()->replaceData($revision, $rawdata);
			$isReplace = ($revision->getId() != $newDraft->getId()) ? true : false;
			
		} catch (\Exception $e) {
			if ($e instanceof NotFoundHttpException) {
				 $this->addFlash('error', $e->getMessage());
			} else {
				 $this->addFlash('error', 'The revision ' . $ouuid . ' can not be replaced');
			}
			$isReplace = false;
		
		}
		return $this->render( 'ajax/notification.json.twig', [
				'success' => $isReplace,
				'revision_id' => $newDraft->getId(),
		]);
	}
	
	
	/**
	 * @Route("/api/{name}/merge/{ouuid}", defaults={"_format": "json"})
	 * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
	 * @Method({"POST"})
	 */
	public function mergeAction($ouuid, ContentType $contentType, Request $request) {
	
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not create content for a managed content type');
		}
	
		$rawdata = json_decode($request->getContent(), true);
		if (empty($rawdata)){
			throw new BadRequestHttpException('Not a valid JSON message');
		}
	
		try {
			$revision = $this->dataService()->getNewestRevision($contentType->getName(), $ouuid);
			$newDraft = $this->dataService()->replaceData($revision, $rawdata, "merge");
			$isMerge = ($revision->getId() != $newDraft->getId()) ? true : false;
			
		} catch (\Exception $e) {
			if ($e instanceof NotFoundHttpException) {
				 $this->addFlash('error', $e->getMessage());
			} else {
				 $this->addFlash('error', 'The revision ' . $ouuid . ' can not be replaced');
			}
			$isMerge = false;
		
		}
		return $this->render( 'ajax/notification.json.twig', [
				'success' => $isMerge,
				'revision_id' => $newDraft->getId(),
		]);
	}
	
	
	/**
	 * @Route("/api/test", defaults={"_format": "json"}, name="api.test")
     * @Method({"GET"})
	 */
	public function testAction(Request $request) {
		return $this->render( 'ajax/notification.json.twig', [
				'success' => true,
		]);
	}
}