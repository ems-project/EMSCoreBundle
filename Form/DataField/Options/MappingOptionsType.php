<?php

namespace Ems\CoreBundle\Form\DataField\Options;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * It's a coumpound field for field specific mapping option.
 * All options defined here are passed to
 * Elasticsearch as field mapping.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class MappingOptionsType extends AbstractType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add ( 'index', ChoiceType::class, [ 
				'required' => false,
				'choices' => [ 
						'Not defined' => null,
						'No' => 'no',
						'Analyzed' => 'analyzed',
						'Not Analyzed' => 'not_analyzed' 
				] 
		] );
	}
}