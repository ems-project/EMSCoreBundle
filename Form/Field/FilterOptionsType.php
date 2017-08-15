<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FilterOptionsType extends AbstractType {
	
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
				],
		] )->add ( 'stopwords', ChoiceType::class, [
				'attr' => ['class' => 'filter_option'],
				'required' => false,
				'choices' => [
						'None' => '_none_',
						'Arabic' => '_arabic_',
						'Armenian' => '_armenian_',
						'Basque' => '_basque_',
						'Brazilian' => '_brazilian_',
						'Bulgarian' => '_bulgarian_',
						'Catalan' => '_catalan_',
						'Cjk' => '_cjk_',
						'Czech' => '_czech_',
						'Danish' => '_danish_',
						'Dutch' => '_dutch_',
						'English' => '_english_',
						'Finnish' => '_finnish_',
						'French' => '_french_',
						'Galician' => '_galician_',
						'German' => '_german_',
						'Greek' => '_greek_',
						'Hindi' => '_hindi_',
						'Hungarian' => '_hungarian_',
						'Indonesian' => '_indonesian_',
						'Irish' => '_irish_',
						'Italian' => '_italian_',
						'Latvian' => '_latvian_',
						'Lithuanian' => '_lithuanian_',
						'Norwegian' => '_norwegian_',
						'Persian' => '_persian_',
						'Portuguese' => '_portuguese_',
						'Romanian' => '_romanian_',
						'Russian' => '_russian_',
						'Sorani' => '_sorani_',
						'Spanish' => '_spanish_',
						'Swedish' => '_swedish_',
						'Turkish' => '_turkish_',
						'Thai' => '_thai_',
				],
		] )->add ( 'ignore_case', CheckboxType::class, [
				'attr' => ['class' => 'filter_option'],
				'required' => false,
		] )->add ( 'remove_trailing', CheckboxType::class, [
				'attr' => ['class' => 'filter_option'],
				'required' => false,
		] );
	}
}