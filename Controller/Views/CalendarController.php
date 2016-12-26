<?php
namespace Ems\CoreBundle\Controller\Views;

use Ems\CoreBundle;
use Ems\CoreBundle\Controller\AppController;
use Ems\CoreBundle\Entity\Form\Search;
use Ems\CoreBundle\Entity\View;
use Ems\CoreBundle\Form\Form\SearchFormType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class CalendarController extends AppController
{
	/**
	 * @Route("/views/calendar/replan/{view}.json", name="views.calendar.replan", defaults={"_format": "json"}))
	 * @Method({"POST"})
	 */
	public function updateAction(View $view, Request $request) {
		try {
			$ouuid = $request->request->get('ouuid', false);
			$type = $view->getContentType()->getName();
			$revision = $this->getDataService()->initNewDraft($type, $ouuid);
			if($revision){
				$rawData = $revision->getRawData();
				$field = $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['dateRangeField']);
				

				/**@var \DateTime $from */
				/**@var \DateTime $to */
				$from = new \DateTime($request->request->get('start', false));
				if($from) {
					$to = $request->request->get('end', false);
					if(!$to){
						$to = clone $from;
						$to->add(new \DateInterval("PT23H59M"));
					}
					else {
						$to = new \DateTime($to);
					}
					
					$input = [
							$field->getMappingOptions()['fromDateMachineName'] => $from->format('c'),
							$field->getMappingOptions()['toDateMachineName'] => $to->format('c'),
					];
					
					if($field->getMappingOptions()['nested']){
						$rawData[$field->getName()] = $input;
					}
					else{
						$rawData = array_merge($rawData, $input);
					}
					
					$revision->setRawData($rawData);
					$this->getDataService()->finalizeDraft($revision);					
				}
				else{
					$this->addFlash('warning', 'From date missing?!');
				}
			}
			else {
				$this->addFlash('warning', 'Object '.$ouuid.' not found');
			}
			return $this->render( 'view/custom/calendar_replan.json.twig', [
					'success' => true,
			] );
		}
		catch(\Exception $e){
			$this->addFlash('error', 'Exception: '.$e->getMessage());
			return $this->render( 'ajax/notification.json.twig', [
				'success' => false,
			] );			
		}
	}
	/**
	 * @Route("/views/calendar/search/{view}.json", name="views.calendar.search", defaults={"_format": "json"}))
	 * @Method({"GET"})
	 */
	public function searchAction(View $view, Request $request) {
		$search = new Search();
		$form = $this->createForm(SearchFormType::class, $search, [
				'method' => 'GET',
				'light' => true,
		]);
		$form->handleRequest($request);
		
		$search = $form->getData();

		$body = $this->getSearchService()->generateSearchBody($search);
		
		/**@var \DateTime $from */
		/**@var \DateTime $to */
		$from = new \DateTime($request->query->get('from'));
		$to = new \DateTime($request->query->get('to'));
		$field = $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['dateRangeField']);
		
		if(empty($body['query']['bool']['must'])){
			$body['query']['bool']['must'] = [];
		}
		
		if($field->getMappingOptions()['nested']){
			$body['query']['bool']['must'][] = [
				'nested' => [
					'path' => $field->getName(),
					'query' => [
						'range' => [
								$field->getName().'.'.$field->getMappingOptions()['fromDateMachineName'] => ['lte' => $to->format('c')]
						]
					]
				]
			];
			$body['query']['bool']['must'][] = [
				'nested' => [
					'path' => $field->getName(),
					'query' => [
						'range' => [
								$field->getName().'.'.$field->getMappingOptions()['toDateMachineName'] => ['gte' => $from->format('c')]
						]
					]
				]
			];
		}
		else {
			$body['query']['bool']['must'][] = [
				'range' => [
					$field->getMappingOptions()['fromDateMachineName'] => ['lte' => $to->format('c')]
				]
			];
			$body['query']['bool']['must'][] = [
				'range' => [
					$field->getMappingOptions()['toDateMachineName'] => ['gte' => $from->format('c')]
				]
			];
		}
		
		$searchQuery = [
				'index' => $view->getContentType()->getEnvironment()->getAlias(),
				'type' => $view->getContentType()->getName(),
				"from" => 0,
				"size" => 1000,
				"body" => $body,
		];
		
		$data = $this->getElasticsearch()->search($searchQuery);
		
		return $this->render( 'view/custom/calendar_search.json.twig', [
				'success' => true,
				'data' => $data,
				'field' => $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['dateRangeField']),
				'contentType' => $view->getContentType(),
				'environment' => $view->getContentType()->getEnvironment(),
		] );
	}
}