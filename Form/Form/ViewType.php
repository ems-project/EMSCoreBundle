<?php

namespace Ems\CoreBundle\Form\Form;

use Ems\CoreBundle\Entity\Template;
use Ems\CoreBundle\Form\Field\IconPickerType;
use Ems\CoreBundle\Form\Field\IconTextType;
use Ems\CoreBundle\Form\Field\SubmitEmsType;
use Ems\CoreBundle\Form\Field\ViewTypePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;


class ViewType extends AbstractType {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var Template $template */
		$template = $builder->getData ();
		
		$builder
		->add ( 'name', IconTextType::class, [
			'icon' => 'fa fa-tag'
		] )
		->add ( 'icon', IconPickerType::class, [
			'required' => false,
		])
		->add ( 'type', ViewTypePickerType::class, [
			'required' => false,
		])
		->add ( 'create', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save' 
		] );
	}
}
