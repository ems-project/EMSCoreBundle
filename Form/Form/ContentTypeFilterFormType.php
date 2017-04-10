<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\EnvironmentService;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeFilterFormType extends AbstractType {
	
	private $circleType;
	//private $choices;
	private $service;
	
	public function __construct($circleType, EnvironmentService $service)
	{
		$this->service = $service;
		$this->circleType = $circleType;
		//$this->choices = null;
	}
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {		
		
		$contentTypesData =  [];
		//TODO: why is this here?
		//http://symfony.com/doc/current/cookbook/form/dynamic_form_modification.html#cookbook-dynamic-form-modification-suppressing-form-validation
		//$builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
		//	$event->stopPropagation();
		//}, 900);
		if(isset($options['data']['request']['contentypes']) && 
				!empty($options['data']['request']['contentypes'])) {
			$contentTypes = explode(",", $options['data']['request']['contentypes']);
			foreach ($contentTypes as $contentType) {
				list($id, $name) = explode(":", $contentType);
				$contentTypesData[] = $id;
			}
		}
		$builder->add('contentType', EntityType::class, [
				'class' => 'EMSCoreBundle:ContentType',
				'query_builder' => function (EntityRepository $er) {
					return $er->createQueryBuilder('ct')
					->where("ct.deleted = false")
					->orderBy('ct.orderKey');
					
				},
				'choice_label' => function ($value, $key, $index) {
					return '<i class="'.$value->getIcon().' text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getSingularName();
				},
				'multiple' => true,
				'required' => false,
				'choice_value' => function ($value) {
					if($value != null && is_object($value)){
						return $value->getId();
					}
					return $value;
				},
				'data' => $contentTypesData,
				'attr' => [
						'class' => 'select2'
				],
		])
		->add('fromuri', HiddenType::class,[
				'attr' => [ 
						'value' => $options['data']['fromuri'],
				]
				])
		->add('filter', SubmitEmsType::class, [
				'attr' => [ 
						'class' => 'btn-primary btn-md' 
				],
				'icon' => 'fa fa-columns'
		]);
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefault ( 'csrf_protection', false );//To prevent forms conflicts with CompareEnvironmentFormType : "The CSRF token is invalid. Please try to resubmit the form."
	}	
}