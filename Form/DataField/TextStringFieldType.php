<?php

namespace Ems\CoreBundle\Form\DataField;

use Ems\CoreBundle\Entity\FieldType;
use Ems\CoreBundle\Form\Field\AnalyzerPickerType;
use Ems\CoreBundle\Form\Field\IconPickerType;
use Ems\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
			
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
 class TextStringFieldType extends DataFieldType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Text field';
	}
	
	/**
	 * Get a icon to visually identify a FieldType
	 * 
	 * @return string
	 */
	public static function getIcon(){
		return 'fa fa-pencil-square-o';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {

		/** @var FieldType $fieldType */
		$fieldType = $options ['metadata'];
		
		if($options['prefixIcon'] || $options['prefixText'] || $options['suffixIcon'] || $options['suffixText'] ){
			$builder->add ( 'text_value', IconTextType::class, [ 
					'required' => false,
					'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
					'label' => (null != $options ['label']?$options ['label']:$fieldType->getName()),
					'icon' => $options['prefixIcon'],
					'prefixText' => $options['prefixText'],
					'suffixIcon' => $options['suffixIcon'],
					'suffixText' => $options['suffixText'],
			] );			
		}
		else{
			$builder->add ( 'text_value', TextType::class, [
					'label' => (null != $options ['label']?$options ['label']:$fieldType->getName()),
					'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
					'required' => false,
			] );			
		}
		
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );
		$resolver->setDefault ( 'prefixIcon', null );
		$resolver->setDefault ( 'prefixText', null );
		$resolver->setDefault ( 'suffixIcon', null );
		$resolver->setDefault ( 'suffixText', null );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
		parent::buildOptionsForm ( $builder, $options );
		$optionsForm = $builder->get ( 'options' );
		
		// String specific display options
		$optionsForm->get ( 'displayOptions' )->add ( 'prefixIcon', IconPickerType::class, [ 
				'required' => false 
		] )->add ( 'prefixText', IconTextType::class, [ 
				'required' => false,
				'icon' => 'fa fa-hand-o-left' 
		] )->add ( 'suffixIcon', IconPickerType::class, [ 
				'required' => false 
		] )->add ( 'suffixText', IconTextType::class, [ 
				'required' => false,
				'icon' => 'fa fa-hand-o-right' 
		] );
		
		// String specific mapping options
		$optionsForm->get ( 'mappingOptions' )->add ( 'analyzer', AnalyzerPickerType::class);
	}
}