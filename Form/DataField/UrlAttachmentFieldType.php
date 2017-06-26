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
use Symfony\Component\Form\Exception\TransformationFailedException;
	
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
		$this->fileService = $fileService;
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
	 * {@inheritdoc}
	 */
	public function getParent()
	{
		return IconTextType::class;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function reverseViewTransform($data, FieldType $fieldType){
		/**@var DataField $out*/
		$dataField= parent::reverseViewTransform($data, $fieldType);
		if(empty($data)){
			if($dataField->getFieldType()->getRestrictionOptions()['mandatory']){
				$dataField->addMessage('This entry is required');
			}
			$dataField->setRawData(['url'=>null, 'content' => ""]);
		}
		elseif(is_string($data)) {
			try {
				$content = file_get_contents($data);
				$rawData = [
					'url' => $data,
					'content' => base64_encode($content),
					'size' => strlen($content),
				];
				$dataField->setRawData($rawData);				
			}
			catch(\Exception $e) {
				$dataField->addMessage(sprintf(
						'Impossible to fetch the ressource due to %s',
						$e->getMessage()
				));
			}
		}
		else {
			$dataField->addMessage(sprintf(
					'Data not supported: %s',
					json_encode($data)
			));
		}
		return $dataField;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function viewTransform(DataField $data) {
		$out = parent::viewTransform($data);
		if( !empty($out)) {
			if(!empty($out['url'])){
				if(is_string($out['url'])){
					return $out['url'];									
				}
				$data->addMessage('Non supported input data : '.json_encode($out));
			}
 		}
 		
 		return "";
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
		] )
		->add ( 'prefixIcon', IconPickerType::class, [
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