<?php

namespace Ems\CoreBundle\Form\Field;

use Ems\CoreBundle\Entity\ContentType;
use Ems\CoreBundle\Service\ContentTypeService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ContentTypePickerType extends ChoiceType {
	
	private $choices;
	
	/**@var ContentTypeService */
	private $service;
	
	public function __construct(ContentTypeService $service)
	{
		parent::__construct();
		$this->service = $service;
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'selectpicker';
	}
	
	/**
	 * @param OptionsResolver $resolver
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		
		
		$this->choices = [];
		$keys = [];
		/**@var ContentType $choice*/
		foreach ($this->service->getAll() as $choice){
			$keys[] = $choice->getName();	
			$this->choices[$choice->getName()] = $choice;
		}
		parent::configureOptions($resolver);
		
		$resolver->setDefaults(array(
			'choices' => $keys,
			'attr' => [
					'data-live-search' => false
			],
			'choice_attr' => function($category, $key, $index) {
				/** @var ContentType $contentType */
				$contentType = $this->choices[$index];
				return [
						'data-content' => '<span class="text-'.$contentType->getColor().'"><i class="'.(empty($contentType->getIcon())?' fa fa-book':$contentType->getIcon()).'"></i>&nbsp;&nbsp;'.$contentType->getName().'</span>'
				];
			},
			'choice_value' => function ($value) {
				return $value;
		    },
		    'multiple' => false,
		));
	}
}

