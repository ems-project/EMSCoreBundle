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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use EMS\CoreBundle\Form\Nature\ReorganizeType;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class HierarchicalViewType extends ViewType {
	
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
		return "Hierarchical: manage a menu structure (based on a ES query)";
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getName(){
		return "Hierarchical";
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
		
		
		$fieldType = new FieldType();
		
		$builder
		->add ( 'parent', DataLinkFieldType::class, [
				'label' => 'Parent',
				'metadata' => $fieldType,
				'type' => $view->getContentType()->getName (),
				'multiple' => false,
				'dynamicLoading' => true
		] )
		->add ( 'size', IntegerType::class, [
				'label' => 'Limit the result to the x first results',
				'attr' => [
				]
		] )
		->add ( 'field', ContentTypeFieldPickerType::class, [
				'label' => 'Target children field (datalink)',
				'required' => false,
				'firstLevelOnly' => false,
				'mapping' => $mapping,
				'types' => [
						'keyword',
						'text', //TODO: for ES2 support
		]]);
		
		$builder->get('parent')->addModelTransformer(new CallbackTransformer(
				function ($raw) {
					$dataField = new DataField();
					$dataField->setRawData($raw);
					return $dataField;
				},
				function (DataField $tagsAsString) {
					// transform the string back to an array
					return $tagsAsString->getRawData();
				}
		));
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'hierarchical_view';
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
		
		if(empty($view->getOptions()['parent'])){
			throw new NotFoundHttpException('Parent menu not found');
		}
		$parentId = explode(':', $view->getOptions()['parent']);
		if(count($parentId) != 2){
			throw new NotFoundHttpException('Parent menu not found: '.$view->getOptions()['parent']);
		}
		
		$parent = NULL;
		try {
			$parent= $this->client->get([
					'index' => $view->getContentType()->getEnvironment()->getAlias(),
					'type' => $parentId[0],
					'id' => $parentId[1],
			]);			
		}
		catch (\Exception $e) {
			throw new NotFoundHttpException('Parent menu not found: '.$view->getOptions()['parent']);
		}
		
		if(empty($parent)){
			throw new NotFoundHttpException('Parent menu not found: '.$view->getOptions()['parent']);
		}
		
		
		$data = [];
		
		$form = $this->formFactory->create(ReorganizeType::class, $data, [
		]);
		
		
		$form->handleRequest($request);
		
		if ($form->isSubmitted()) {
			$data = $form->getData();
			$structure = json_decode($data['structure'], true);

			$this->reorder($view->getOptions()['parent'], $view, $structure);
			
			$this->session->getFlashBag()->add('notice', 'The '.$view->getContentType()->getPluralName().' have been reorganized');
			
			
			
			return new RedirectResponse($this->router->generate('data.draft_in_progress', [
					'contentTypeId' => $view->getContentType()->getId(),
			], UrlGeneratorInterface::RELATIVE_PATH));
		}
		
		
		$response = new Response();
		$response->setContent($this->twig->render('EMSCoreBundle:view:custom/'.$this->getBlockPrefix().'.html.twig', [
				'parent' => $parent,
				'view' => $view,
				'form' => $form->createView(),
				'contentType' => $view->getContentType(),
				'environment' => $view->getContentType()->getEnvironment(),
		]));
		return $response;
	}
	
	function reorder($itemKey, View $view, $structure) {
		$temp = explode(':', $itemKey);
		$type = $temp[0];
		$ouuid = $temp[1];
		try {
			$revision = $this->dataService->initNewDraft($type, $ouuid);
			$data = $revision->getRawData();
			$data[$view->getOptions()['field']] = [];
			foreach ($structure as $item){
				$data[$view->getOptions()['field']][] = $item['ouuid'];
				if(explode(':', $item['ouuid'])[0] == $view->getContentType()->getName()){
					$this->reorder($item['ouuid'], $view, $item['children']);
				}
			}
			$revision->setRawData($data);
			$this->dataService->finalizeDraft($revision);
		}
		catch (\Exception $e) {
			$this->session->getFlashBag()->add('warning', 'It was impossible to update the item '.$itemKey.': '.$e->getMessage());
		}
	}
	
}