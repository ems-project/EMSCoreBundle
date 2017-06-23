<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Revision;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormRegistryInterface;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;

class RevisionType extends AbstractType {
	
	/**@var FormRegistryInterface**/
	private $formRegistry;
	
	public function __construct(FormRegistryInterface $formRegistry){
		$this->formRegistry =$formRegistry;
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var Revision $revision */
		$revision = $builder->getData ();
		
		$builder->add ( 'raw_data', $revision->getContentType()->getFieldType()->getType(), [ 
				'metadata' => $revision->getContentType()->getFieldType(),
				'error_bubbling' => false,
		] )->add ( 'save', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save' 
		] );
		
		$builder->get ( 'raw_data' )
		->addModelTransformer(new DataFieldModelTransformer($revision->getContentType()->getFieldType(), $this->formRegistry))
		->addViewTransformer(new DataFieldViewTransformer($revision->getContentType()->getFieldType(), $this->formRegistry));
		
		if($options['has_clipboard']){
			$builder->add ( 'paste', SubmitEmsType::class, [
					'attr' => [
							'class' => 'btn-primary btn-sm '
					],
					'icon' => 'fa fa-paste'
			] );
		}
		
		if($options['has_copy']){
			$builder->add ( 'copy', SubmitEmsType::class, [
					'attr' => [
							'class' => 'btn-primary btn-sm '
					],
					'icon' => 'fa fa-copy'
			] );
		}
		
		if($revision->getDraft()){
			$builder->add ( 'publish', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'glyphicon glyphicon-open' ,
				'label' => 'Finalize draft'
			] );
		}
		$builder->add ( 'allFieldsAreThere', HiddenType::class, [
				 'data' => true,
		] );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults ( array (
				'compound' => true,
            	'csrf_protection' => false,
				'data_class' => 'EMS\CoreBundle\Entity\Revision',
				'has_clipboard' => false,
				'has_copy' => false,
		) );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'revision';
	}
}
