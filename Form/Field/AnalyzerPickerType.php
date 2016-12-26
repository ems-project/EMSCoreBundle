<?php

namespace Ems\CoreBundle\Form\Field;

use Symfony\Component\OptionsResolver\OptionsResolver;

class AnalyzerPickerType extends SelectPickerType {
	
	private $choices = [
    				'Not defined' => null,
    				'Standard' => 'standard', 
    				'Arabic' => 'arabic', 
    				'Armenian' => 'armenian', 
    				'Basque' => 'basque', 
    				'Brazilian' => 'brazilian', 
    				'Bulgarian' => 'bulgarian', 
    				'Catalan' => 'catalan', 
    				'Cjk' => 'cjk', 
    				'Czech' => 'czech',
    				'Danish' => 'danish', 
    				'Dutch' => 'dutch', 
    				'English' => 'english', 
    				'Finnish' => 'finnish', 
    				'French' => 'french', 
    				'Galician' => 'galician', 
   					'German' => 'german', 
   					'Greek' => 'greek', 
   					'Hindi' => 'hindi', 
   					'Hungarian' => 'hungarian', 
    				'Indonesian' => 'indonesian', 
    				'Irish' => 'irish', 
    				'Italian' => 'italian', 
    				'Latvian' => 'latvian', 
    				'Lithuanian' => 'lithuanian', 
   					'Norwegian' => 'norwegian', 
   					'Persian' => 'persian', 
    				'Portuguese' => 'portuguese', 
    				'Romanian' => 'romanian', 
    				'Russian' => 'russian', 
    				'Sorani' => 'sorani', 
   					'Spanish' => 'spanish', 
   					'Swedish' => 'swedish', 
   					'Turkish' => 'turkish', 
   					'Thai' => 'thai'
	];
	
	/**
	 * @param OptionsResolver $resolver
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'required' => false,
			'choices' => $this->choices,
			'attr' => [
					'data-live-search' => true
			],
			'choice_attr' => function($category, $key, $index) {
				return [
						'data-content' => $this->humanize($key)
				];
			},
			'choice_value' => function ($value) {
		       return $value;
		    },
		));
	}
}
