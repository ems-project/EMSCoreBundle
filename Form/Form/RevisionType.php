<?php

namespace Ems\CoreBundle\Form\Form;

use Ems\CoreBundle\Entity\Revision;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Ems\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class RevisionType extends AbstractType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var Revision $revision */
		$revision = $builder->getData ();
		
		$builder->add ( 'dataField', $revision->getContentType ()->getFieldType ()->getType (), [ 
				'metadata' => $revision->getContentType ()->getFieldType (),
				'error_bubbling' => false,
		] )->add ( 'save', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save' 
		] );
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
				'data_class' => 'Ems\CoreBundle\Entity\Revision',
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
