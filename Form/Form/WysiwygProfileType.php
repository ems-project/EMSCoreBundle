<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class WysiwygProfileType extends AbstractType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		
		$builder
		->add ( 'name', IconTextType::class, [
				'icon' => 'fa fa-tag',
				'label' => 'Profile\'s name',
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
		
// 		$builder->get ( 'config' )->addModelTransformer ( new CallbackTransformer( 
// 				function ($profileAsJSON) {
// 					// transform the json to a string
// 					return json_encode($profileAsJSON);
// 				}, 
// 				function ($profileAsString) {
// 					// transform the string back to an json
// 					return json_decode( $profileAsString);
// 				}
// 		));
		
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
