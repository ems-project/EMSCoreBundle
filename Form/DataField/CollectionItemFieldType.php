<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Form\Field\SubmitEmsType;

/**
 * Defined a Nested obecjt.
 * It's used to  groups subfields together.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class CollectionItemFieldType extends DataFieldType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Collection item object (this message should neve seen anywhere)';
	}	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getIcon(){
		return 'fa fa-question';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'collectionitemtype';
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		/* get the metadata associate */
		/** @var FieldType $fieldType */
		$fieldType = $builder->getOptions () ['metadata'];
		
		/** @var FieldType $fieldType */
		foreach ( $fieldType->getChildren () as $fieldType ) {

			if (! $fieldType->getDeleted ()) {
				/* merge the default options with the ones specified by the user */
				$options = array_merge ( [ 
						'metadata' => $fieldType,
						'label' => false 
				], $fieldType->getDisplayOptions () );
				$builder->add ( 'ems_' . $fieldType->getName (), $fieldType->getType (), $options );
			}
		}
		
		$builder->add ( 'remove_collection_item', SubmitEmsType::class, [
				'attr' => [
						'class' => 'btn-danger btn-sm remove-content-button'
				],
				'label' => 'Remove',
				'icon' => 'fa fa-trash'
		] );
	}
	
// 	/**
// 	 *
// 	 * {@inheritdoc}
// 	 *
// 	 */
// 	public function buildView(FormView $view, FormInterface $form, array $options) {
// 		/* give options for twig context */
// 		parent::buildView ( $view, $form, $options );
// 	}
	
// 	/**
// 	 *
// 	 * {@inheritdoc}
// 	 *
// 	 */
// 	public function configureOptions(OptionsResolver $resolver) {
// 		/* set the default option value for this kind of compound field */
// 		parent::configureOptions ( $resolver );
// 	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if($data->getFieldType () == null){
			$tmp = [];
			/** @var DataField $child */
			foreach ($data->getChildren() as $child){
				$className = $child->getFieldType()->getType();
				$class = new $className;
				$class->buildObjectArray($child, $tmp);
			}
			$out [] = $tmp;
		}
		else if (! $data->getFieldType ()->getDeleted ()) {
			$out [$data->getFieldType ()->getName ()] = [];
		}
	}



	public function isNested(){
		return true;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function isContainer() {
		/* this kind of compound field may contain children */
		return true;
	}
	
// 	/**
// 	 *
// 	 * {@inheritdoc}
// 	 *
// 	 */
// 	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
// 		parent::buildOptionsForm ( $builder, $options );
// 		$optionsForm = $builder->get ( 'options' );
// 		// nested doesn't not have that much options in elasticsearch
// 		$optionsForm->remove ( 'mappingOptions' );
// 		// an optional icon can't be specified ritgh to the container label
// 		$optionsForm->get ( 'displayOptions' )->add ( 'icon', IconPickerType::class, [ 
// 				'required' => false 
// 		] );
// 	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function generateMapping(FieldType $current, $withPipeline) {
		return [
			$current->getName() => [
				"type" => "nested",
				"properties" => [],
		]];
	}
}
