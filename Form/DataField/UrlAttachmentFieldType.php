<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Form\FormRegistryInterface;
	
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class UrlAttachmentFieldType extends DataFieldType {
	

	/**@var FileService */
	private $fileService;
	
	
	
	public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, FileService $fileService) {
		parent::__construct($authorizationChecker, $formRegistry);
		$this->fileService= $fileService;
	}
	

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getIcon() {
		return 'fa fa-cloud-download';
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Url Attachment (indexed) field';
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		/** @var FieldType $fieldType */
		$fieldType = $options ['metadata'];
		$builder->add ( 'input_value', IconTextType::class, [
				'label' => (null != $options ['label']?$options ['label']:$fieldType->getName()),
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				'required' => false,
				'icon' => $options['icon'],
		] );
	}
	


	public function convertInput(DataField $dataField) {
		
		if(!empty($dataField->getInputValue())){
			$content = file_get_contents($dataField->getInputValue());
			$rawData = [
				'url' => $dataField->getInputValue(),
				'content' => base64_encode($content),
				'size' => strlen($content),
			];
			
			$dataField->setRawData($rawData);
		}
		else{
			$dataField->setRawData(['content' => ""]);
		}
	}	
	
	public function generateInput(DataField $dataField){
		
		
		$rawData = $dataField->getRawData();
		
		if(!empty($rawData) && !empty($rawData['url'])){
			$dataField->setInputValue($rawData['url']);
		}
		else {
			$dataField->setInputValue(null);			
		}
		return $this;
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
		parent::buildOptionsForm ( $builder, $options );
		$optionsForm = $builder->get ( 'options' );
		$optionsForm->remove ( 'mappingOptions' );
		$optionsForm->get ( 'displayOptions' )
			->add ( 'icon', IconPickerType::class, [ 
					'required' => false 
			] );
	}


	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType ()->getDeleted ()) {
			/**
			 * by default it serialize the text value.
			 * It can be overrided.
			 */
			$out [$data->getFieldType ()->getName ()] = $data->getRawData();
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
		$resolver->setDefault ( 'icon', null );
	}
	
	/**
	 * {@inheritdoc}
	 */
	public static function generateMapping(FieldType $current, $withPipeline){
		$body = [
				"type" => "nested",
				"properties" => [
						"url" => [
							"type" => "string",
						],
						"size" => [
							"type" => "long",
						]
				],
			];
		
		if($withPipeline) {
			$body['properties']['content'] =[
				"type" => "text",
				"index" => "no",
				'fields' => [
					'keyword' => [
						'type' => 'keyword',
						'ignore_above' => 256
					]
				]
			];
		}
		
		return [
			$current->getName() => array_merge($body,  array_filter($current->getMappingOptions()))
		];
	}
	
	/**
	 * {@inheritdoc}
	 */
	public static function generatePipeline(FieldType $current){
		return [
			"attachment" => [
				'field' => $current->getName().'.content',
				'target_field' => $current->getName().'.attachment',
				'indexed_chars' => 1000000,
			]
		];
	}
}