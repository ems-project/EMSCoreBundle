<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class AnalyzerOptionsType extends AbstractType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add ( 'type', ChoiceType::class, [ 
				'choices' => [
						'Standard' => 'standard',
						'Stop' => 'stop',
						'Pattern' => 'pattern',
						'Fingerprint' => 'fingerprint',
						'Custom' => 'custom',
				],
		] );
	}
}