<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\ObjectChoiceLoader;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use Elasticsearch\Client;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
																	
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
	
 *        
 */
 class DataLinkFieldType extends DataFieldType {

 	/**@var Client $client*/
 	private $client;
 	/**@var FormRegistryInterface $registry*/
 	private $registry;
 	
	public function setClient(Client $client){
		$this->client = $client;
		return $this;
	}
 	
 	public function setRegistry(FormRegistryInterface $registry){
 		$this->registry = $registry;
	 	return $this;
 	}
 	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Link to data object(s)';
	}

	/**
	 * Get Elasticsearch subquery
	 *
	 * @return array
	 */
	public function getElasticsearchQuery(DataField $dataField, array $options = [])
	{
		$opt = array_merge([
				'nested' => '',
		], $options);
		if(strlen($opt['nested'])){
			$opt['nested'] .= '.'; 
		}
		
		$data = $dataField->getRawData();
		$out = [];
		if(is_array($data)){
			$out = [
				'terms' => [
						$opt['nested'].$dataField->getFieldType()->getName() => $data
				]
			];
		}
		else{
			$out = [
					'term' => [
							$opt['nested'].$dataField->getFieldType()->getName() => $data
					]
			];
		}
		
		return $out;
	}
	
	/**
	 * Get a icon to visually identify a FieldType
	 * 
	 * @return string
	 */
	public static function getIcon(){
		return 'fa fa-sitemap';
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType ()->getDeleted ()) {
			$options = $data->getFieldType()->getDisplayOptions();
			if(isset($options['multiple']) && $options['multiple']){
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
	public function buildForm(FormBuilderInterface $builder, array $options) {

		/** @var FieldType $fieldType */
		$fieldType = $options ['metadata'];
		
		//Add an event listener in order to sort existing normData before the merge in MergeCollectionListener
		$listener = function (FormEvent $event) {
			$data = $event->getForm()->getNormData();
			$rawData = $data->getRawData();
			if(!empty($rawData)){
				usort($rawData, function($a, $b) use ($event){
					if(!empty($event->getData()['array_text_value'])){
						$indexA = array_search($a, $event->getData()['array_text_value']);
						$indexB = array_search($b, $event->getData()['array_text_value']);
						if($indexA === false || $indexA > $indexB) return 1;
						if($indexB === false || $indexA < $indexB) return -1;
					}
					return 0;
				});
				$data->setRawData($rawData);
				
				$event->getForm()->setData($data);				
			}
			
		};
		
		$builder->add ( $options['multiple']?'array_text_value':'text_value', ObjectPickerType::class, [
				'label' => (null != $options ['label']?$options ['label']:$fieldType->getName()),
				'required' => false,
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				'multiple' => $options['multiple'],
				'type' => $options['type'],
				'dynamicLoading' => $options['dynamicLoading'],
				'sortable' => $options['sortable'],
		] );
		
		if($options['sortable']){
			$builder->addEventListener(FormEvents::PRE_SUBMIT, $listener);				
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
		$resolver->setDefault ( 'multiple', false );
		$resolver->setDefault ( 'type', null );
		$resolver->setDefault ( 'environment', null );
		$resolver->setDefault ( 'defaultValue', null );
		$resolver->setDefault ( 'required', false );
		$resolver->setDefault ( 'sortable', false );
		$resolver->setDefault ( 'dynamicLoading', true );
	}


	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getDefaultOptions($name) {
		$out = parent::getDefaultOptions($name);
		
		$out['displayOptions']['dynamicLoading'] = true;
		$out['mappingOptions']['index'] = 'not_analyzed';
	
		return $out;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getChoiceList(FieldType $fieldType, array $choices){
		
		/**@var ObjectPickerType $objectPickerType*/
		$objectPickerType = $this->registry->getType(ObjectPickerType::class)->getInnerType();
		
		
		/**@var ObjectChoiceLoader $loader */
		$loader = $objectPickerType->getChoiceListFactory()->createLoader($fieldType->getDisplayOptions()['type'],  true /*count($choices) == 0 || !$fieldType->getDisplayOptions()['dynamicLoading']*/);
		$all = $loader->loadAll();
		if(count($choices) > 0){
			foreach ($all as $key => $data){
				if(! in_array($key, $choices)){
					unset($all[$key]);
				}
			}
// 			return $loader->loadChoiceList()->loadChoices($choices);
		}
		return $all;
		
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
		$optionsForm->get ( 'displayOptions' )->add ( 'multiple', CheckboxType::class, [ 
				'required' => false,
		] )->add ( 'dynamicLoading', CheckboxType::class, [
				'required' => false,
		] )->add ( 'sortable', CheckboxType::class, [
				'required' => false,
		] )->add ( 'type', TextType::class, [ 
				'required' => false,
		] )->add ( 'defaultValue', TextType::class, [ 
				'required' => false,
		] );
		
	}
}