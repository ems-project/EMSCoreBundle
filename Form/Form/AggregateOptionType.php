<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class AggregateOptionType extends AbstractType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		
		$builder
		->add ( 'name', IconTextType::class, [
				'icon' => 'fa fa-tag',
				'label' => 'Aggregate Option\'s name',
		] )
		->add ( 'config', CodeEditorType::class, [
				'language' => 'ace/mode/json'
		] )
		->add ( 'save', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save' 
		] );
		
		if(! $options['createform']){
			$builder->add('remove', SubmitEmsType::class, [
					'attr' => [
							'class' => 'btn-primary btn-sm '
					],
					'icon' => 'fa fa-trash'
			] );
		}
	}
	
	public function configureOptions(OptionsResolver $resolver){
		$resolver->setDefaults ( array (
				'createform' => false,
		) );
	}
}
