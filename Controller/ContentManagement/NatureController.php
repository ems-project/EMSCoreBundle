<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Nature\ReorderType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class NatureController extends AppController
{
	
	const MAX_ELEM = 400;

	/**
	 * @Route("/content-type/nature/reorder/{contentType}", name="nature.reorder"))
	 */
	public function reorderAction(ContentType $contentType, Request $request) {
		if($contentType->getOrderField() == null || $contentType->getFieldType()->__get('ems_'.$contentType->getOrderField()) == null ){
			$this->addFlash('warning', 'This content type does not have any order field');

			return $this->redirectToRoute('data.draft_in_progress', [
					'contentTypeId' => $contentType->getId(),
			]);
		}
		

		if($contentType->getFieldType()->__get('ems_'.$contentType->getOrderField())->getRestrictionOptions()['minimum_role'] != null && !$this->isGranted($contentType->getFieldType()->__get('ems_'.$contentType->getOrderField())->getRestrictionOptions()['minimum_role'])){
			return $this->redirectToRoute('data.draft_in_progress', [
					'contentTypeId' => $contentType->getId(),
			]);			
		}
		
		$result = $this->getElasticsearch()->search([
				'index' => $contentType->getEnvironment()->getAlias(),
				'type' => $contentType->getName(),
				'size' => 400,
				'body' => [
						'sort' => $contentType->getOrderField(),
				]
		]);
		
		if($result['hits']['total'] > $this::MAX_ELEM) {
			$this->addFlash('warning', 'This content type have to much elements to reorder them all in once');
		}
		
		
		$data = [];

		$form = $this->createForm(ReorderType::class, $data, [
				'result' => $result,
		]);
		

		$form->handleRequest($request);
		

		/** @var \EMS\CoreBundle\Service\DataService $dataService*/
		$dataService = $this->get('ems.service.data');
		$counter = 1;
		
		if ($form->isSubmitted()) {
			foreach($request->request->get('reorder')['items'] as $itemKey => $value){
				try {
					$revision = $dataService->initNewDraft($contentType->getName(), $itemKey);
					$data = $revision->getRawData();
					$data[$contentType->getOrderField()] = $counter++;
					$revision->setRawData($data);
					$dataService->finalizeDraft($revision);					
				}
				catch (\Exception $e) {
					$this->addFlash('warning', 'It was impossible to update the item '.$itemKey.': '.$e->getMessage());
				}
			}

			$this->addFlash('notice', 'The '.$contentType->getPluralName().' have been reordered');
			
			return $this->redirectToRoute('data.draft_in_progress', [
					'contentTypeId' => $contentType->getId(),
			]);
		}

		return $this->render( 'EMSCoreBundle:nature:reorder.html.twig', [
				'contentType' => $contentType,
				'form' => $form->createView(),
				'result' => $result,
 		] );
	}
}