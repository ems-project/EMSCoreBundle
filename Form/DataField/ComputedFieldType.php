<?php

namespace EMS\CoreBundle\Form\DataField;




use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\DataField;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ComputedFieldType extends DataFieldType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Computed from the raw-data';
	}	
	


	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function generateMapping(FieldType $current, $withPipeline){
		if(!empty($current->getMappingOptions()) && !empty($current->getMappingOptions()['mappingOptions'])){
			try{
				$mapping = json_decode($current->getMappingOptions()['mappingOptions']);
				return [ $current->getName() =>  $this->elasticsearchService->updateMapping($mapping) ];
			}
			catch(\Exception $e) {
				//TODO send message to user, mustr move to service first
			}
		}
		return [];
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getIcon(){
		return 'fa fa-gears';
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
	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
		parent::buildOptionsForm ( $builder, $options );
		$optionsForm = $builder->get ( 'options' );
		
		// String specific display options
		$optionsForm->get ( 'displayOptions' )->add ( 'valueTemplate', TextareaType::class, [ 
				'required' => false,
				'attr' => [
					'rows' => 8,
				],
		] )->add ( 'json', CheckboxType::class, [ 
				'required' => false,
				'label' => 'Try to JSON decode'
		] )->add ( 'displayTemplate', TextareaType::class, [ 
				'required' => false,
				'attr' => [
					'rows' => 8,
				],
		] );


		$optionsForm->get ( 'mappingOptions' )->remove('index')->remove('analyzer')->add('mappingOptions', TextareaType::class, [ 
				'required' => false,
				'attr' => [
					'rows' => 8,
				],
		] )
		->add ( 'copy_to', TextType::class, [
				'required' => false,
		] );
		$optionsForm->remove('restrictionOptions');
		$optionsForm->remove('migrationOptions');
		
	}

	public function getBlockPrefix() {
		return  'empty';
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );
		$resolver->setDefault ( 'displayTemplate', NULL );
		$resolver->setDefault ( 'json', false );
		$resolver->setDefault ( 'valueTemplate', NULL );
	}
	

}