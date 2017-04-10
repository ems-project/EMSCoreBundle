<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Form\View\ViewType;
use Elasticsearch\Client;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class CalendarViewType extends ViewType {

	
	private $twig;
	
	/** @var Client $client */
	private $client;
	
	public function __construct($twig, $client){
		$this->twig = $twig;
		$this->client = $client;
	}
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return "Calendar: a view where you can planify your object";
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getName(){
		return "Calendar";
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		parent::buildForm($builder, $options);
		$builder->add ( 'dateRangeField', TextType::class, [
		] )->add ( 'timeFormat', TextType::class, [
				'attr' => [
						'placeholder' => 'i.e. H(:mm)',
				]
		] )->add ( 'locale', TextType::class, [
				'attr' => [
						'placeholder' => 'i.e. fr',
				]
		] )->add ( 'firstDay', IntegerType::class, [
				'attr' => [
						'placeholder' => 'Sunday=0, Monday=1, Tuesday=2, etc.',
				]
		] )->add ( 'weekends', CheckboxType::class, [
		] )->add ( 'slotDuration', TextType::class, [
				'attr' => [
						'placeholder' => 'i.e. 00:30:00',
				]
		] );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'calendar_view';
	}
	

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getParameters(View $view, FormFactoryInterface $formFactoty, Request $request) {
		

		$search = new Search();
		$form = $formFactoty->create(SearchFormType::class, $search, [
				'method' => 'GET',
				'light' => true,
		]);
		
		$form->handleRequest($request);
		
		return [
			'view' => $view,
			'field' => $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['dateRangeField']),
			'contentType' => $view->getContentType(),
			'environment' => $view->getContentType()->getEnvironment(),
			'form' => $form->createView(),
		];
	}
	
}