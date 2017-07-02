<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class IntegerFieldType extends DataFieldType {

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Integer field';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getIcon(){
		return 'glyphicon glyphicon-sort-by-order';
	}
	
// 	/**
// 	 *
// 	 * {@inheritdoc}
// 	 *
// 	 */
// 	public function importData(DataField $dataField, $sourceArray, $isMigration) {
// 		$migrationOptions = $dataField->getFieldType()->getMigrationOptions();
// 		if(!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
// 			$dataField->setIntegerValue($sourceArray);
// 		}
// 		return [$dataField->getFieldType()->getName()];
// 	}
	
	
	


	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function isValid(DataField &$dataField){
		$isValid = parent::isValid($dataField);
		
		$rawData = $dataField->getRawData();
		if(! empty($rawData) && !is_numeric($rawData)) {
			$isValid = FALSE;
			$dataField->addMessage("Not a integer");
		}
		
		return $isValid;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var FieldType $fieldType */
		$fieldType = $builder->getOptions () ['metadata'];
	
		$builder->add ( 'value', TextType::class, [
				'label' => (isset($options['label'])?$options['label']:$fieldType->getName()),
				'required' => false,
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				'attr' => [
						//'class' => 'spinner',
				]
		] );
	}

// 	/**
// 	 *
// 	 * {@inheritdoc}
// 	 *
// 	 */
// 	public function isValid(DataField &$dataField){
// 		$isValid = parent::isValid ( $dataField );

// 		if($dataField->getRawData() !== null && (!intval($dataField->getRawData()) || $dataField->getRawData() === '0' || $dataField->getRawData() === 0 )){
// 			$dataField->addMessage("Misformated integer ".$dataField->getRawData());
// 			return FALSE;
// 		}
		
// 		return $isValid;
// 	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType ()->getDeleted ()) {
			/**
			 * by default it serialize the text value.
			 * It must be overrided.
			 */
			$out [$data->getFieldType ()->getName ()] = $data->getIntegerValue();
		}
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function generateMapping(FieldType $current, $withPipeline){
		return [
				$current->getName() => array_merge(["type" => "integer"],  array_filter($current->getMappingOptions()))
		];
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
		parent::buildOptionsForm ( $builder, $options );
		$optionsForm = $builder->get ( 'options' );
		
	
// 		// String specific display options
// 		$optionsForm->get ( 'displayOptions' )->add ( 'choices', TextareaType::class, [
// 				'required' => false,
// 		] )->add ( 'labels', TextareaType::class, [
// 				'required' => false,
// 		] );
	
// 		// String specific mapping options
// 		$optionsForm->get ( 'mappingOptions' )->add ( 'analyzer', AnalyzerPickerType::class);
	}	
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \EMS\CoreBundle\Form\DataField\DataFieldType::getBlockPrefix()
	 */
	public function getBlockPrefix() {
		return 'bypassdatafield';
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
	 */
	public function viewTransform(DataField $dataField){
		$out = parent::viewTransform($dataField);
		return ['value' => $out];
	}
	
	public function reverseViewTransform($data, FieldType $fieldType) {
		$temp = (!empty($data) && isset($data['value']))?$data['value']:null;
		return parent::reverseViewTransform($temp, $fieldType);
	}
}