<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\DataService;
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
			
			if (($e instanceof NotFoundHttpException) OR ($e instanceof BadRequestHttpException)) {
				throw $e;
			} else {
				$this->addFlash('error', 'The revision for contenttype '. $contentType->getName() .' can not be created. Reason:'.$e->getMessage());
			}
			$isCreated = false;
			return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
					'success' => $isCreated,
				    'ouuid' => $ouuid,
					'type' => $contentType->getName(),
			]);
		}
		
		return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
				'success' => $isCreated,
				'revision_id' => $newRevision->getId(),
			    'ouuid' => $newRevision->getOuuid(),
		]);
	}
	
	/**
	 * @Route("/api/{name}/{ouuid}", defaults={"ouuid": null, "_format": "json"})
	 * @Route("/api/{name}/get/{ouuid}", defaults={"ouuid": null, "_format": "json"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     * @Method({"GET"})
	 */
	public function getAction($ouuid, ContentType $contentType) {
		
		try {
			$revision = $this->dataService()->getNewestRevision($contentType->getName(), $ouuid);
			
			$isFound = (isset($revision) && !empty($revision)) ?  true : false;
			
		} catch (\Exception $e) {
			
			$isFound = false;
			if (($e instanceof NotFoundHttpException) OR ($e instanceof BadRequestHttpException)) {
				throw $e;
			} else {
				$this->addFlash('error', 'The revision for contenttype '. $contentType->getName() .' can not be found. Reason: '.$e->getMessage());
			}
			return $this->render( 'EMSCoreBundle:ajax:revision.json.twig', [
					'success' => $isFound,
					'ouuid' => $ouuid,
					'type' => $contentType->getName(),
			]);
		}
		
		return $this->render( 'EMSCoreBundle:ajax:revision.json.twig', [
				'success' => $isFound,
				'revision' => $revision->getRawData(),
				'ouuid' => $revision->getOuuid(),
				'id' => $revision->getId(),
		]);
	}
	
	/**
	 * @Route("/api/{name}/finalize/{id}", defaults={"_format": "json"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     * @Method({"POST"})
	 */
	public function finalizeAction($id, ContentType $contentType, Request $request) {
		
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not finalize content for a managed content type');	
		}
		
		$out = [
			'success' => 'false',
		];
		try {
			$revision = $this->dataService()->getRevisionById($id, $contentType);
			$newRevision = $this->dataService()->finalizeDraft($revision);
			$out['success'] = !$newRevision->getDraft();
			$out['ouuid'] = $newRevision->getOuuid();
			
		} catch (\Exception $e) {
			
			if (($e instanceof NotFoundHttpException) OR ($e instanceof DataStateException)) {
				throw $e;
			} else {
				$this->addFlash('error', 'The revision ' . $id . ' for contenttype '. $contentType->getName() .' can not be finalized. Reason: '.$e->getMessage());
			}
			$out['success'] = false;
		}
		return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', $out);
	}
	
	/**
	 * @Route("/api/{name}/discard/{id}", defaults={"_format": "json"})
	 * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
	 * @Method({"POST"})
	 */
	public function discardAction($id, ContentType $contentType, Request $request) {
	
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not discard content for a managed content type');
		}
	
		try {
			$revision = $this->dataService()->getRevisionById($id, $contentType);
			$this->dataService()->discardDraft($revision);
			$isDiscard = ($revision->getId() != $id) ? true : false;

		} catch (\Exception $e) {
			$isDiscard = false;
			if (($e instanceof NotFoundHttpException) OR ($e instanceof BadRequestHttpException)) {
				throw $e;
			} else {
				 $this->addFlash('error', 'The revision ' . $id . ' for contenttype '. $contentType->getName() .' can not be discarded. Reason: '.$e->getMessage());
			}
			return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
					'success' => $isDiscard,
					'type' => $contentType->getName(),
					'revision_id' => $id,
			]);
		}
		return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
				'success' => $isDiscard,
				'type' => $contentType->getName(),
				'revision_id' => $revision->getId(),
		]);
	}
	
	/**
	 * @Route("/api/{name}/delete/{ouuid}", defaults={"_format": "json"})
	 * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
	 * @Method({"POST"})
	 */
	public function deleteAction($ouuid, ContentType $contentType, Request $request) {
	
		$isDeleted = false;
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not delete content for a managed content type');
		}
	
		try {
			$this->dataService()->delete($contentType->getName(), $ouuid);
			try {
				$revision = $this->dataService()->getNewestRevision($contentType->getName(), $ouuid);
			} catch (\Exception $exception){
				if ($exception instanceof NotFoundHttpException) {
					$isDeleted = true;
				}
			}
	
		} catch (\Exception $e) {
			$isDeleted = false;
			if (($e instanceof NotFoundHttpException) OR ($e instanceof BadRequestHttpException)) {
				throw $e;
			} else {
				$this->addFlash('error', 'The revision ' . $id . ' can not be deleted. Reason: '.$e->getMessage());
			}
		}
		return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
				'success' => $isDeleted,
				'ouuid' => $ouuid,
				'type' => $contentType->getName(),
		]);
	}
	
	/**
	 * @Route("/api/{name}/replace/{ouuid}", defaults={"_format": "json"})
	 * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
	 * @Method({"POST"})
	 */
	public function replaceAction($ouuid, ContentType $contentType, Request $request) {
	
		if(!$contentType->getEnvironment()->getManaged()){
			throw new BadRequestHttpException('You can not replace content for a managed content type');	
		}
		
		$rawdata = json_decode($request->getContent(), true);
		if (empty($rawdata)){
			throw new BadRequestHttpException('Not a valid JSON message');	
		}
		
		try {
			$revision = $this->dataService()->getNewestRevision($contentType->getName(), $ouuid);
			$newDraft = $this->dataService()->replaceData($revision, $rawdata);
			$isReplaced = ($revision->getId() != $newDraft->getId()) ? true : false;
			
		} catch (\Exception $e) {
			$isReplaced = false;
			if ($e instanceof NotFoundHttpException) {
				throw $e;
			} else {
				 $this->addFlash('error', 'The revision ' . $ouuid . ' for contenttype '. $contentType->getName() .' can not be replaced. Reason: '.$e->getMessage());
			}
			return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
					'success' => $isReplaced,
					'ouuid' => $ouuid,
					'type' => $contentType->getName(),
					'revision_id' => null,
			]);
		}
		return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
				'success' => $isReplaced,
				'ouuid' => $ouuid,
				'type' => $contentType->getName(),
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
			throw new BadRequestHttpException('You can not merge content for a managed content type');
		}
	
		$rawdata = json_decode($request->getContent(), true);
		if (empty($rawdata)){
			throw new BadRequestHttpException('Not a valid JSON message for revision ' . $ouuid . ' and contenttype '. $contentType->getName());
		}
	
		try {
			$revision = $this->dataService()->getNewestRevision($contentType->getName(), $ouuid);
			$newDraft = $this->dataService()->replaceData($revision, $rawdata, "merge");
			$isMerged = ($revision->getId() != $newDraft->getId()) ? true : false;
			
		} catch (\Exception $e) {
			if ($e instanceof NotFoundHttpException) {
				 throw $e;
			} else {
				 $this->addFlash('error', 'The revision ' . $ouuid . ' for contenttype '. $contentType->getName() .' can not be merged. Reason: '.$e->getMessage());
			}
			$isMerged = false;
			return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
					'success' => $isMerged,
					'ouuid' => $ouuid,
					'type' => $contentType->getName(),
					'revision_id' => null,
			]);
		}
		return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
				'success' => $isMerged,
				'ouuid' => $ouuid,
				'type' => $contentType->getName(),
				'revision_id' => $newDraft->getId(),
		]);
	}
	
	/**
	 * @Route("/api/test", defaults={"_format": "json"}, name="api.test")
     * @Method({"GET"})
	 */
	public function testAction(Request $request) {
		return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
				'success' => true,
		]);
	}
}