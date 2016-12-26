<?php

namespace Ems\CoreBundle\Form\Form;

use Ems\CoreBundle\Entity\Revision;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Ems\CoreBundle\Form\Field\SubmitEmsType;

class RebuildIndexType extends AbstractType {
	/**
	 *
	 * @param FormBuilderInterface $builder        	
	 * @param array $options        	
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var Revision $revision */
		$revision = $builder->getData ();
		$builder->add ( 'option', ChoiceType::class, [ 
				'label' => '',
				'expanded' => true,
				'choices' => [ 
						'newIndex',
						'sameIndex'
				],
				'choice_label' => function ($category, $key, $index) {
					if ('newIndex' == $category)
						return 'A new index will be created and all objects will be reindexed with the revision defined for this environment. Once it\'s done the environement alias will be updated. Nothing will be removed from the current search index.';
					if ('sameIndex' == $category)
						return 'All object in eMS will be just reindexed into the existing index.';
					return 'Unknow option';
				} 
		]
		// 'choice_attr' => function ($category, $key, $index) {
		// return [
		// 'class' => 'category_' . strtolower ( $category )
		// ];
		// },
		// 'group_by' => function ($category, $key, $index) {
		// // randomly assign things into 2 groups
		// return rand(0, 1) == 1 ? 'Group A' : 'Group B';
		// },
		// 'preferred_choices' => function ($category, $key, $index) {
		// return $category == 'Cat2' || $category == 'Cat3';
		// }
		 )->add ( 'rebuild', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-recycle' 
		] );
	}
}
