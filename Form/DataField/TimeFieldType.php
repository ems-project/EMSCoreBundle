<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeFieldType extends DataFieldType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Time field';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getIcon(){
		return 'fa fa-clock-o';
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

			$timeObject = \DateTime::createFromFormat($format, $sourceArray);
			$dataField->setRawData($timeObject->format(\DateTime::ISO8601));
		}
		return [$dataField->getFieldType()->getName()];
	}
	
	public static function getFormat($options){
		
		if($options['displayOptions']['showMeridian']){
			$format = "g:i";
		}
		else {
			$format = "G:i";
		}
		
		if($options['displayOptions']['showSeconds']){
			$format .= ":s";
		}
		
		if($options['displayOptions']['showMeridian']){
			$format .= " A";
		}
		return $format;
	}
	
	/**
	 * {@inheritdoc}
	 *
	 */
	public function setDataValue($input, DataField &$dataField, array $options){
		$format = $this->getFormat($options);

		$converted = \DateTime::createFromFormat($format, $input);
		if($converted){
			$dataField->setRawData($converted->format(\DateTime::ISO8601));
		}		
		else {
			$dataField->setRawData(null);
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 */
	public function getDataValue(DataField &$dataField, array $options){

		if(null !== $dataField->getRawData()){

			if(is_array($dataField->getRawData()) && count($dataField->getRawData()) === 0){
				return null; //empty array means null/empty
			}
			
			$format = $this->getFormat($options);
			/**@var \DateTime $converted*/
			$dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $dataField->getRawData());
			return $dateTime->format($format);
		}
		
		return null;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var FieldType $fieldType */
		$fieldType = $builder->getOptions()['metadata'];
		
		$attr = [
			'class' => 'timepicker',
			'data-show-meridian' => $options['showMeridian']?'true':'false',
// 			'data-provide' => 'timepicker', //for lazy-mode
			'data-default-time'  => $options['defaultTime'],
			'data-show-seconds'  => $options['showSeconds'],
			'data-explicit-mode'  => $options['explicitMode'],
		];
		
		if($options['minuteStep']){
			$attr['data-minute-step'] = $options['minuteStep'];
		}
		
		$builder->add ( 'data_value', TextType::class, [
				'label' => (isset($options['label'])?$options['label']:$fieldType->getName()),
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				'required' => false,
				'attr' =>  $attr
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
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'timefieldtype';
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );
		$resolver->setDefault ( 'minuteStep', 15 );
		$resolver->setDefault ( 'showMeridian', false );
		$resolver->setDefault ( 'defaultTime', 'current' );
		$resolver->setDefault ( 'showSeconds', false );
		$resolver->setDefault ( 'explicitMode', true );
	}
	
	/**
	 * {@inheritdoc}
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType()->getDeleted ()) {
			
			$format = $data->getFieldType()->getMappingOptions()['format'];
			
			$format = DateFieldType::convertJavaDateFormat($format);
			
			if(null !== $data->getRawData() && (!is_array($data->getRawData()) || count($data->getRawData()) !== 0)){

				/**@var \DateTime $converted*/
				$dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $data->getRawData());
				$out [$data->getFieldType ()->getName ()] = $dateTime->format($format);
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
		$optionsForm->get ( 'mappingOptions' )->add ( 'format', TextType::class, [
				'required' => false,
				'empty_data' => 'HH:mm:ss',
				'attr' => [
					'placeholder' => 'i.e. HH:mm:ss'
				],
		] );

		$optionsForm->get ( 'displayOptions' )->add ( 'minuteStep', IntegerType::class, [
				'required' => false,
				'empty_data' => 15,
		]);
		$optionsForm->get ( 'displayOptions' )->add ( 'showMeridian', CheckboxType::class, [
				'required' => false,
				'label' => 'Show meridian (true: 12hr, false: 24hr)'
		]);
		$optionsForm->get ( 'displayOptions' )->add ( 'defaultTime', TextType::class, [
				'required' => false,
 				'label' => 'Default time (empty: current time, \'11:23\': specific time, \'false\': do not set a default time)'
		]);
		$optionsForm->get ( 'displayOptions' )->add ( 'showSeconds', CheckboxType::class, [
				'required' => false,
		]);
		$optionsForm->get ( 'displayOptions' )->add ( 'explicitMode', CheckboxType::class, [
				'required' => false,
		]);
	
	}
}