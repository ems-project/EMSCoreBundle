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
use EMS\CoreBundle\Form\DataTransformer\DataFieldTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;

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
		
		$itemFieldType = new FieldType();
		$itemFieldType->setParent($fieldType);
		$itemFieldType->setType(CollectionItemFieldType::class);
		
		$builder->addViewTransformer(new DataFieldViewTransformer($itemFieldType, $this->formRegistry))
			->addModelTransformer(new DataFieldModelTransformer($itemFieldType, $this->formRegistry));
		
		/** @var FieldType $fieldType */
		foreach ( $fieldType->getChildren () as $fieldType ) {

			if (! $fieldType->getDeleted ()) {
				/* merge the default options with the ones specified by the user */
				$options = array_merge ( [ 
						'metadata' => $fieldType,
						'label' => false 
				], $fieldType->getDisplayOptions () );
				$builder->add ( $fieldType->getName (), $fieldType->getType(), $options );
				$builder->get($fieldType->getName ())
					->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry))
					->addModelTransformer(new DataFieldModelTransformer($fieldType, $this->formRegistry));
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
// 				$className = $child->getFieldType()->getType();
// 				$class = new $className;
				$class =$this->formRegistry->getType($child->getFieldType()->getType());
				$class->buildObjectArray($child, $tmp);
			}
			$out [] = $tmp;
		}
		else if (! $data->getFieldType ()->getDeleted ()) {
			$out [$data->getFieldType ()->getName ()] = [];
		}
	}



	public static function isNested(){
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
