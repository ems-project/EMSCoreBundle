<?php 

namespace EMS\CoreBundle\Form\Form;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmsCollectionType extends CollectionType{

	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \Symfony\Component\Form\Extension\Core\Type\CollectionType::buildForm()
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		/** @var FieldType $fieldType */
		$fieldType = clone $builder->getOptions () ['metadata'];
		$options['metadata'] = $fieldType;
		
		$entryOptions = $fieldType->getDisplayOptions();

		$options = array_merge($options, [
				'entry_type' => CollectionItemFieldType::class,
				'entry_options' => [
						'metadata' => $fieldType,
				],
				'allow_add' => true,
				'allow_delete' => true,
				'prototype' => true,
				'required' => false,
		]);
		
// 		$options['disabled'] = !$this->authorizationChecker->isGranted($fieldType->getMinimumRole());

		parent::buildForm($builder, $options);
	}
	
	public function configureOptions(OptionsResolver $resolver){
		
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );
		$resolver->setDefaults([
				'collapsible' => false,
				'icon' => null,
				'itemBootstrapClass' => null,
				'singularLabel' => null,
				'sortable' => false,
		]);
	}
	
}