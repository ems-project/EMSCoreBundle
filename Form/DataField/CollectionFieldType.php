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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use EMS\CoreBundle\Form\DataTransformer\DataFieldTransformer;
use EMS\CoreBundle\Form\Form\EmsCollectionType;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
class CollectionFieldType extends DataFieldType {
	
	protected $dataService;
	
	
	
	public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, $service_container) {
		parent::__construct($authorizationChecker, $formRegistry);
		$this->service_container= $service_container;
	}
	
	
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
			
			$dataService = $this->service_container->get('ems.service.data');
			
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
						$dataService->updateDataStructure($childFieldType, $grandChild);
						if(is_array($item)) {
							$dataService->updateDataValue($grandChild, $item, $isMigration);							
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
	 * {@inheritDoc}
	 * @see \Symfony\Component\Form\AbstractType::getParent()
	 */
	public function getParent() {
		return EmsCollectionType::class;
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
		$view->vars ['collapsible'] = $options ['collapsible'];
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
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function isContainer() {
		/* this kind of compound field may contain children */
		return true;
	}
	
	public static function isCollection(){
		return true;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function isValid(DataField &$dataField, DataField $parent=null){
		$isValid = TRUE;
		//Madatory Validation
		//$isValid = $isValid && $this->isMandatory($dataField);
		
		$restrictionOptions = $dataField->getFieldType()->getRestrictionOptions();
		
		if(!empty($restrictionOptions['min']) && count($dataField->getRawData()) < $restrictionOptions['min'])  {
			if($restrictionOptions['min'] == 1){
				$dataField->addMessage("At least 1 item is required");				
			}
			else {
				$dataField->addMessage("At least ".$restrictionOptions['min']." items are required");
			}
			$isValid = FALSE;
		}
		
		if(!empty($restrictionOptions['max']) && count($dataField->getRawData()) > $restrictionOptions['max'])  {
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
		$optionsForm->get ( 'restrictionOptions' )->remove('mandatory_if');
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
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
	 */
	public function reverseViewTransform($data, FieldType $fieldType){
		$cleaned = [];
		foreach ( $data as $item ){
			//if the item _ems_item_reverseViewTransform is missing it means that this item hasn't been submitted (and it can be deleted)
			if(!empty($item) && isset($item['_ems_item_reverseViewTransform'])) {
				unset($item['_ems_item_reverseViewTransform']);
				$cleaned[] = $item;
			}
		}
		$out = parent::reverseViewTransform($cleaned, $fieldType);
		return $out;
	}
}
