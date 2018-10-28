<?php

namespace EMS\CoreBundle\Form\Subform;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchFilterType extends AbstractType {

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {

		$builder->add('field', TextType::class, [
				'required' => false,
		]);

		$builder->add('boost', $options['is_super']?NumberType::class:HiddenType::class, [
			'required' => false,
		]);
		
		$builder->add('operator', ChoiceType::class, [
			'choices' => [
				'Query (and)' => 'query_and',
				'Query (or)' => 'query_or', 
				'Match (and)' => 'match_and',
				'Match (or)' => 'match_or', 
				'Term' => 'term', 
			]
		]);
		
		$builder->add('booleanClause', ChoiceType::class, [
			'choices' => [
				'Must' => 'must',
				'Should' => 'should', 
				'Must not' => 'must_not',
				'Filter' => 'filter', 
			]
		]);
		
		$builder->add('pattern', TextType::class, [
			'required' => false,
		]);
	}
	
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults([
            'data_class' => 'EMS\CoreBundle\Entity\Form\SearchFilter',
            'is_super' => false,
		]);
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'search_filter';
	}
	
}
