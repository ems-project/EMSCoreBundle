<?php

namespace EMS\CoreBundle\Form\DataField\Options;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use EMS\CoreBundle\Form\Field\RolePickerType;

/**
 * It's a coumpound field for field specific restriction option.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class RestrictionOptionsType extends AbstractType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder
		->add ( 'mandatory', CheckboxType::class, [
				'required' => false,
		])
		->add ( 'minimum_role', RolePickerType::class, [
				'required' => false,
		])
		;
	}
}