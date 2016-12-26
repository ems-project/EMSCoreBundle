<?php

namespace Ems\CoreBundle\Form\DataField;

use Ems\CoreBundle\Entity\DataField;
use Ems\CoreBundle\Entity\DataValue;
use Ems\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateFieldType extends DataFieldType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Date field';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getIcon(){
		return 'fa fa-calendar';
	}
	
	/**
	 * {@inheritdoc}
	 *
	 */
	public function getDataValue(DataField &$dataField, array $options){
		
		$format = DateFieldType::convertJavaDateFormat($options['displayOptions']['displayFormat']);

		$dates = [];
		if(null !== $dataField->getRawData()){
			foreach ($dataField->getRawData() as $dataValue){
				/**@var \DateTime $converted*/
				$dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $dataValue);
				if($dateTime){
					$dates[] = $dateTime->format($format);
				}
				else{
					$dates[] = null;
					//TODO: should add a flash message
				}
			}			
		}
		return implode(',', $dates);
	}
	
	/**
	 * {@inheritdoc}
	 *
	 */
	public function setDataValue($input, DataField &$dataField, array $options){
		
		$format = DateFieldType::convertJavaDateFormat($options['displayOptions']['displayFormat']);
		if($options['displayOptions']['multidate']){
			$dates = explode(',', $input);
		}
		else{
			$dates = [$input];
		}
		
		$convertedDates = [];
		
		foreach ($dates as $idx => $date){
			/**@var \DateTime $converted*/
			$converted = \DateTime::createFromFormat($format, $date);
			if($converted){
				$convertedDates[] = $converted->format(\DateTime::ISO8601);
			}
		}
		
		$dataField->setRawData($convertedDates);
	}


	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'datefieldtype';
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function importData(DataField $dataField, $sourceArray, $isMigration) {
		$migrationOptions = $dataField->getFieldType()->getMigrationOptions();
		if(!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
			$format = $dataField->getFieldType()->getMappingOptions()['format'];	
			$format = DateFieldType::convertJavaDateFormat($format);
		
			if(null == $sourceArray) {
				$sourceArray = [];
			}
			if(is_string($sourceArray)){
				$sourceArray = [$sourceArray];
			}
			$data = [];
			foreach ($sourceArray as $idx => $child){
				$dateObject = \DateTime::createFromFormat($format, $child);
				$data[] = $dateObject->format(\DateTime::ISO8601);
			}
			$dataField->setRawData($data);
		}
		return [$dataField->getFieldType()->getName()];
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );	
		$resolver->setDefault ( 'displayFormat', 'dd/mm/yyyy' );
		$resolver->setDefault ( 'todayHighlight', false );
		$resolver->setDefault ( 'weekStart', 1 );
		$resolver->setDefault ( 'daysOfWeekHighlighted', '' );
		$resolver->setDefault ( 'daysOfWeekDisabled', '' );
		$resolver->setDefault ( 'multidate', '' );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var FieldType $fieldType */
		$fieldType = $builder->getOptions () ['metadata'];
	
		$builder->add ( 'data_value', TextType::class, [
				'label' => (isset($options['label'])?$options['label']:$fieldType->getName()),
				'required' => false,
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				'attr' => [
					'class' => 'datepicker',
					'data-date-format' => $fieldType->getDisplayOptions()['displayFormat'],
					'data-today-highlight' => $fieldType->getDisplayOptions()['todayHighlight'],
					'data-week-start' => $fieldType->getDisplayOptions()['weekStart'],
					'data-days-of-week-highlighted' => $fieldType->getDisplayOptions()['daysOfWeekHighlighted'],
					'data-days-of-week-disabled' => $fieldType->getDisplayOptions()['daysOfWeekDisabled'],
					'data-multidate' => $fieldType->getDisplayOptions()['multidate']?"true":"false",
				] 
		] );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function generateMapping(FieldType $current, $withPipeline){
		return [
				$current->getName() => array_merge([
						"type" => "date",
				],  array_filter($current->getMappingOptions()))
		];
	}
	
	/**
	 * {@inheritdoc}
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType()->getDeleted ()) {
			$format = $data->getFieldType()->getMappingOptions()['format'];
			$multidate = $data->getFieldType()->getDisplayOptions()['multidate'];
			
			$format = DateFieldType::convertJavaDateFormat($format);
			

			if($multidate){
				$dates = [];
				if(null !== $data->getRawData()){
					foreach ($data->getRawData() as $dataValue){
						/**@var \DateTime $converted*/
						$dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $dataValue);
						$dates[] = $dateTime->format($format);
					}
				}				
			}
			else {
				$dates = null;
				if(null !== $data->getRawData() && count($data->getRawData()) >= 1){
					/**@var \DateTime $converted*/
					$dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $data->getRawData()[0]);
					if($dateTime) {
						$dates = $dateTime->format($format);						
					}
					else{
						//TODO: at least a warning
						$dates = null;
					}
				}
			}
			
			$out [$data->getFieldType ()->getName ()] = $dates;
				
		}
	}
	
	public static function convertJavaDateFormat($format){
		$dateFormat = $format;
		//TODO: naive approch....find a way to comvert java date format into php
		$dateFormat = str_replace('dd', 'd', $dateFormat);
		$dateFormat = str_replace('MM', 'm', $dateFormat);
		$dateFormat = str_replace('yyyy', 'Y', $dateFormat);
		$dateFormat = str_replace('hh', 'g', $dateFormat);
		$dateFormat = str_replace('HH', 'G', $dateFormat);
		$dateFormat = str_replace('mm', 'i', $dateFormat);
		$dateFormat = str_replace('ss', 's', $dateFormat);
		$dateFormat = str_replace('aa', 'A', $dateFormat);
		
		
		
		return $dateFormat;
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
		$optionsForm->get ( 'mappingOptions' )->add ( 'format', TextType::class, [
				'required' => false,
				'empty_data' => 'yyyy/MM/dd',
				'attr' => [
						'placeholder' => 'i.e. yyyy/MM/dd'
				],
		] );	
		
 		// String specific display options
		$optionsForm->get ( 'displayOptions' )->add ( 'displayFormat', TextType::class, [
				'required' => false,
				'empty_data' => 'dd/mm/yyyy',
				'attr' => [
					'placeholder' => 'i.e. dd/mm/yyyy'
				],
		] );
		$optionsForm->get ( 'displayOptions' )->add ( 'weekStart', IntegerType::class, [
				'required' => false,
				'empty_data' => 0,
				'attr' => [
					'placeholder' => '0'
				],
		] );
		$optionsForm->get ( 'displayOptions' )->add ( 'todayHighlight', CheckboxType::class, [
				'required' => false,
		] );
		$optionsForm->get ( 'displayOptions' )->add ( 'multidate', CheckboxType::class, [
				'required' => false,
		] );
		$optionsForm->get ( 'displayOptions' )->add ( 'daysOfWeekDisabled', TextType::class, [
				'required' => false,
				'attr' => [
					'placeholder' => 'i.e. 0,6'
				],
		] );
		$optionsForm->get ( 'displayOptions' )->add ( 'daysOfWeekHighlighted', TextType::class, [
				'required' => false,
				'attr' => [
					'placeholder' => 'i.e. 0,6'
				],
		] );
	
	}
}