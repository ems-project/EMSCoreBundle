<?php

namespace Ems\CoreBundle\Form\DataField;

use Ems\CoreBundle\Entity\DataField;
use Ems\CoreBundle\Entity\FieldType;
use Ems\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\FormBuilderInterface;
								
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
 class IconFieldType extends DataFieldType {	
	
	/**
	 * Get a icon to visually identify a FieldType
	 * 
	 * @return string
	 */
	public static function getIcon(){
		return 'fa fa-flag';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Icon field';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		/** @var FieldType $fieldType */
		$fieldType = $options ['metadata'];
		$builder->add ( 'text_value', IconPickerType::class, [
				'label' => (null != $options ['label']?$options ['label']:'Icon field type'),
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				'required' => false,
		] );
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getDefaultOptions($name) {
		$out = parent::getDefaultOptions($name);
		
		$out['mappingOptions']['index'] = 'not_analyzed';
	
		return $out;
	}
}