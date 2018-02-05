<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use EMS\CoreBundle\Form\DataField\DateFieldType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class FileType extends AbstractType {


	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		$builder->add ( 'sha1', HiddenType::class, [
			'attr' => [
					'class' => 'sha1'
			],
			'required' => $options['required'],
		])
		->add('mimetype', TextType::class, [
			'attr' => [
					'class' => 'type'
			],
			'required' => $options['required'],
		])
		->add('filename', TextType::class, [
				'attr' => [
						'class' => 'name'
				],
				'required' => $options['required'],
		])
		->add('_date', TextType::class, [
				'attr' => [
						'class' => '_date'
				],
				'required' => false,
		])
		->add('_author', TextType::class, [
				'attr' => [
						'class' => '_author'
				],
				'required' => false,
		])
		->add('_language', TextType::class, [
				'attr' => [
						'class' => '_language'
				],
				'required' => false,
		])
		->add('_content', TextareaType::class, [
				'attr' => [
						'class' => '_content',
						'rows' => 6,
				],
				'required' => false,
		]);
		
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'filetype';
	}
	
}