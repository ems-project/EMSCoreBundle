<?php

namespace Ems\CoreBundle\Form\DataField;

use Ems\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Ems\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Ems\CoreBundle\Entity\DataField;

class SelectFieldType extends DataFieldType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Select field';
	}
	
	/**
	 * Get a icon to visually identify a FieldType
	 * 
	 * @return string
	 */
	public static function getIcon(){
		return 'fa fa-caret-square-o-down';
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var FieldType $fieldType */
		$fieldType = $builder->getOptions () ['metadata'];
		
		$choices = [];
		$values = explode("\n", str_replace("\r", "", $options['choices']));
		$labels = explode("\n", str_replace("\r", "", $options['labels']));
		
		foreach ($values as $id => $value){
			if(isset($labels[$id]) && strlen($labels[$id]) > 0){
				$choices[$labels[$id]] = $value;
			}
			else {
				$choices[$value] = $value;
			}
		}
		
		$builder->add ( $options['multiple']?'array_text_value':'text_value', ChoiceType::class, [ 
				'label' => (isset($options['label'])?$options['label']:$fieldType->getName()),
				'required' => false,
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				'choices' => $choices,
    			'empty_data'  => null,
				'multiple' => $options['multiple'],
		] );
	}
	

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );
		$resolver->setDefault ( 'choices', [] );
		$resolver->setDefault ( 'labels', [] );
		$resolver->setDefault ( 'multiple', false );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType ()->getDeleted ()) {
			if($data->getFieldType()->getDisplayOptions()['multiple']){
				$out [$data->getFieldType ()->getName ()] = $data->getArrayTextValue();
			}
			else{
				parent::buildObjectArray($data, $out);
			}
			
		}
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
		$optionsForm->get ( 'displayOptions' )
		->add ( 'multiple', CheckboxType::class, [ 
				'required' => false,
		] )->add ( 'choices', TextareaType::class, [ 
				'required' => false,
		] )->add ( 'labels', TextareaType::class, [ 
				'required' => false,
		] );
		
		// String specific mapping options
		$optionsForm->get ( 'mappingOptions' )->add ( 'analyzer', AnalyzerPickerType::class);
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getDefaultOptions($name) {
		$out = parent::getDefaultOptions($name);
		
		$out['mappingOptions']['index'] = 'not_analyzed';
	
		return $out;
	}
}