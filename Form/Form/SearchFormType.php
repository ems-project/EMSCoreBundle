<?php

namespace Ems\CoreBundle\Form\Form;

use Ems\CoreBundle\Form\Field\ContentTypePickerType;
use Ems\CoreBundle\Form\Field\EnvironmentPickerType;
use Ems\CoreBundle\Form\Field\SubmitEmsType;
use Ems\CoreBundle\Form\Subform\SearchFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchFormType extends AbstractType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		$builder->add('filters', CollectionType::class, array(
				'entry_type'   => SearchFilterType::class,
				'allow_add'    => true,
		));
		if($options['light']){
			$builder->add('applyFilters', SubmitEmsType::class, [
				'attr' => [ 
						'class' => 'btn-primary btn-md',
				],
				'icon' => 'fa fa-check',
			]);
		}
		else{
			$builder->add('sortBy', TextType::class, [
					'required' => false,
			]);
			$builder->add('sortOrder', ChoiceType::class, [
					'choices' => [
							'Ascending' => 'asc',
							'Descending' => 'desc',
					],
					'required' => false,
			]);
			$builder->add('search', SubmitEmsType::class, [
					'attr' => [ 
							'class' => 'btn-primary btn-md' 
					],
					'icon' => 'fa fa-search'
			])->add('exportResults', SubmitEmsType::class, [
					'attr' => [
							'class' => 'btn-primary btn-sm'
					],
					'icon' => 'glyphicon glyphicon-export',
			])->add('environments', EnvironmentPickerType::class, [
				'multiple' => true,
				'required' => false,
		    	'managedOnly' => false,
			])->add('contentTypes', ContentTypePickerType::class, [
				'multiple' => true,
				'required' => false,
			]);			
			if(!$options['savedSearch']){
				$builder->add('save', SubmitEmsType::class, [
						'attr' => [ 
								'class' => 'btn-primary btn-md' 
						],
						'icon' => 'fa fa-save',
				]);
				
			}
		}
		
	}
	
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults([
				'data_class' => 'Ems\CoreBundle\Entity\Form\Search',
				'savedSearch' => false,
				'csrf_protection' => false,
				'light' => false,
		]);
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildView(FormView $view, FormInterface $form, array $options) {
		/* give options for twig context */
		parent::buildView ( $view, $form, $options );
	}
}