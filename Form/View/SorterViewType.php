<?php

namespace EMS\CoreBundle\Form\View;

use Elasticsearch\Client;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\View\ViewType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Form\Nature\ReorderType;
use EMS\CoreBundle\Service\DataService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Router;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class SorterViewType extends ViewType {
	
	/**@var Session $session*/
	protected $session;
	/**@var DataService */
	protected $dataService;
	/**@var Router */
	protected $router;
	
	public function __construct($formFactory, $twig, $client, Session $session, DataService $dataService, Router $router){
		parent::__construct($formFactory, $twig, $client);
		$this->session= $session;
		$this->dataService = $dataService;
		$this->router= $router;
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return "Sorter: order a sub set (based on a ES query)";
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getName(){
		return "Sorter";
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		parent::buildForm($builder, $options);
		
		/**@var View $view */
		$view = $options['view'];
		
		$mapping = $this->client->indices ()->getMapping ( [
				'index' => $view->getContentType()->getEnvironment ()->getAlias(),
				'type' => $view->getContentType()->getName ()
		] );
		
		$mapping = array_values($mapping)[0]['mappings'][$view->getContentType()->getName ()]['properties'];
		
		
		$builder
		->add ( 'body', CodeEditorType::class, [
				'label' => 'The Elasticsearch body query [JSON Twig]',
				'attr' => [
				],
				'slug' => 'report_query',
		] )
		->add ( 'size', IntegerType::class, [
				'label' => 'Limit the result to the x first results',
		] )
		->add ( 'field', ContentTypeFieldPickerType::class, [
				'label' => 'Target order field (integer)',
				'required' => false,
				'firstLevelOnly' => false,
				'mapping' => $mapping,
				'types' => [
						'integer',
		]]);
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'sorter_view';
	}
	

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getParameters(View $view, FormFactoryInterface $formFactoty, Request $request) {
		
		return [];
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function generateResponse(View $view, Request $request) {
		
		try {
			$renderQuery = $this->twig->createTemplate($view->getOptions()['body'])->render([
					'view' => $view,
					'contentType' => $view->getContentType(),
					'environment' => $view->getContentType()->getEnvironment(),
			]);
		}
		catch (\Exception $e){
			$renderQuery = "{}";
		}
		
		$boby = json_decode($renderQuery, true);
		
		$boby['sort'] = [
				$view->getOptions()['field'] => [
						'order' => 'asc',
						"missing" => "_last",
				]
		];
		
		$searchQuery = [
				'index' => $view->getContentType()->getEnvironment()->getAlias(),
				'type' => $view->getContentType()->getName(),
				'body' => $boby,
		];
		
		$searchQuery['size'] = 100;
		if(isset($view->getOptions()['size'])){
			$searchQuery['size'] = $view->getOptions()['size'];
		}
		
		$result = $this->client->search($searchQuery);
		
		if($result['hits']['total'] > $searchQuery['size']) {
			$this->session->getFlashBag()->add('warning', 'This content type have to much elements to reorder them all in once');
		}
		
		$data = [];
		
		$form = $this->formFactory->create(ReorderType::class, $data, [
				'result' => $result,
		]);
		
		
		$form->handleRequest($request);
		
		if ($form->isSubmitted()) {
			$counter = 1;
			foreach($request->request->get('reorder')['items'] as $itemKey => $value){
				try {
					$revision = $this->dataService->initNewDraft($view->getContentType()->getName(), $itemKey);
					$data = $revision->getRawData();
					$data[$view->getOptions()['field']] = $counter++;
					$revision->setRawData($data);
					$this->dataService->finalizeDraft($revision);
				}
				catch (\Exception $e) {
					$this->session->getFlashBag()->add('warning', 'It was impossible to update the item '.$itemKey.': '.$e->getMessage());
				}
			}
			
			$this->session->getFlashBag()->add('notice', 'The '.$view->getContentType()->getPluralName().' have been reordered');
			
			
			
			return new RedirectResponse($this->router->generate('data.draft_in_progress', [
					'contentTypeId' => $view->getContentType()->getId(),
			]));
// 			return $this->redirectToRoute();
		}
		
		
		$response = new Response();
		$response->setContent($this->twig->render('EMSCoreBundle:view:custom/'.$this->getBlockPrefix().'.html.twig', [
				'result' => $result,
				'view' => $view,
				'form' => $form->createView(),
				'contentType' => $view->getContentType(),
				'environment' => $view->getContentType()->getEnvironment(),
		]));
		return $response;
	}
	
}