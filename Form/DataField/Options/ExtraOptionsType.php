<?php

namespace EMS\CoreBundle\Form\DataField\Options;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * It's a coumpound field for field specific extra option.
 *
 */
class ExtraOptionsType extends AbstractType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add ( 'extra', TextareaType::class, [ 
				'attr' => [
					'rows' => 8,
				],
				'required' => false,
		] );
	}
}