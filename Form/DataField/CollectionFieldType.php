<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class CollectionFieldType extends DataFieldType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Collection (manage array of children types)';
	}	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getIcon(){
		return 'fa fa-plus fa-rotate';
	}	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function importData(DataField $dataField, $sourceArray, $isMigration){
		$migrationOptions = $dataField->getFieldType()->getMigrationOptions();
		if(!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
			if(!is_array($sourceArray)){
				$sourceArray = [$sourceArray];
			}
			
			$dataField->getChildren()->clear();
			foreach ($sourceArray as $idx => $item){
				$colItem = new DataField();
				$colItem->setOrderKey($idx);
				$colItem->setFieldType(NULL); // it's a collection item
				foreach ($dataField->getFieldType()->getChildren() as $childFieldType){
					/**@var FieldType $childFieldType */
					if(!$childFieldType->getDeleted()){
						$grandChild = new DataField();
						$grandChild->setOrderKey(0);	
						$grandChild->setParent($colItem);
						$grandChild->setFieldType($childFieldType);
						$grandChild->updateDataStructure($childFieldType);
						if(is_array($item)) {
							$grandChild->updateDataValue($item, $isMigration);							
						}
						else  {
							//TODO: add flash message
						}
						
						$colItem->addChild($grandChild);
					}
				}
		
				$dataField->addChild($colItem);	
				$colItem->setParent($dataField);
			}
		}
		return [$dataField->getFieldType()->getName()];
		
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		/* get the metadata associate */
		/** @var FieldType $fieldType */
		$fieldType = clone $builder->getOptions () ['metadata'];
		
		$builder->add('ems_' . $fieldType->getName(), CollectionType::class, array(
				// each entry in the array will be an "email" field
				'entry_type' => CollectionItemFieldType::class,
				// these options are passed to each "email" type
				'entry_options' => $options,
				'allow_add' => true,
				'allow_delete' => true,
				'prototype' => true,
				'entry_options' => [
						'metadata' => $fieldType,
						'disabled' => !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				],
		))->add ( 'add_nested', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm add-content-button' 
				],
				'label' => 'Add',
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				'icon' => 'fa fa-plus' 
		] );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildView(FormView $view, FormInterface $form, array $options) {
		/* give options for twig context */
		parent::buildView ( $view, $form, $options );
		$view->vars ['icon'] = $options ['icon'];
		$view->vars ['singularLabel'] = $options ['singularLabel'];
		$view->vars ['itemBootstrapClass'] = $options ['itemBootstrapClass'];
		$view->vars ['sortable'] = $options ['sortable'];
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );
		/* an optional icon can't be specified ritgh to the container label */
		$resolver->setDefault ( 'icon', null );
		$resolver->setDefault ( 'singularLabel', null );
		$resolver->setDefault ( 'collapsible', false );
		$resolver->setDefault ( 'sortable', false );
		$resolver->setDefault ( 'itemBootstrapClass', null );
	}
	
// 	/**
// 	 *
// 	 * {@inheritdoc}
// 	 *
// 	 */
// 	public static function buildObjectArray(DataField $data, array &$out) {
		
		
// 	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function isContainer() {
		/* this kind of compound field may contain children */
		return true;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function isValid(DataField &$dataField){
		$isValid = TRUE;
		//Madatory Validation
		//$isValid = $isValid && $this->isMandatory($dataField);
		
		$restrictionOptions = $dataField->getFieldType()->getRestrictionOptions();
		
		if(!empty($restrictionOptions['min']) && $dataField->getChildren()->count() < $restrictionOptions['min'])  {
			if($restrictionOptions['min'] == 1){
				$dataField->addMessage("At least ".$restrictionOptions['min']." item is required");				
			}
			else {
				$dataField->addMessage("At least ".$restrictionOptions['min']." items are required");
			}
			$isValid = FALSE;
		}
		
		if(!empty($restrictionOptions['max']) && $dataField->getChildren()->count() > $restrictionOptions['max'])  {
			$dataField->addMessage("Too many items (max ".$restrictionOptions['max'].")");
			$isValid = FALSE;
		}
		
		
		return $isValid;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
		parent::buildOptionsForm ( $builder, $options );
		$optionsForm = $builder->get ( 'options' );
		// container aren't mapped in elasticsearch
		$optionsForm->remove ( 'mappingOptions' );
		// an optional icon can't be specified ritgh to the container label
		$optionsForm->get ( 'displayOptions' )->add ( 'singularLabel', TextType::class, [ 
				'required' => false 
		] )->add ( 'itemBootstrapClass', TextType::class, [ 
				'required' => false 
		] )->add ( 'icon', IconPickerType::class, [ 
				'required' => false 
		] )->add ( 'collapsible', CheckboxType::class, [ 
				'required' => false 
		] )->add ( 'sortable', CheckboxType::class, [ 
				'required' => false 
		] );
		
		$optionsForm->get ( 'restrictionOptions' )
		->add ( 'min', IntegerType::class, [
				'required' => false
		] )->add ( 'max', IntegerType::class, [
				'required' => false
		] );
		$optionsForm->get ( 'restrictionOptions' )->remove('mandatory');
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType ()->getDeleted ()) {
			$out [$data->getFieldType ()->getName ()] = [];
		}
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'collectionfieldtype';
	}


	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getJsonName(FieldType $current){
		return $current->getName();
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function generateMapping(FieldType $current, $withPipeline) {
		return [$current->getName () => [
				'type' => 'nested',
				'properties' => []
		]];
	}
}
