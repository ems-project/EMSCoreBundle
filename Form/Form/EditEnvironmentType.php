<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class EditEnvironmentType extends AbstractType {
	/**
	 *
	 * @param FormBuilderInterface $builder        	
	 * @param array $options        	
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var Revision $revision */
		$revision = $builder->getData ();
		
		$builder
		->add ( 'name', IconTextType::class, [
			'icon' => 'fa fa-tag'
		] )
		->add ( 'color', ColorPickerType::class, [
				'required' => false,
		]);
		if (array_key_exists('type', $options) && $options['type']) {
			$builder->add ( 'circles', ObjectPickerType::class, [
					'required' => false,
					'type' => $options['type'],
					'multiple' => true,
			]);
		}
		$builder->add ( 'baseUrl', TextType::class, [
				'required' => false,
		])->add ( 'inDefaultSearch', CheckboxType::class, [
			'required' => false,
		])->add ( 'extra', TextareaType::class, [
			'required' => false,
			'attr' => [
				'rows' => '6',
			]
		])
		->add ( 'save', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save' 
		] );
                
                foreach ($options['indexes'] as $index => $info) {
                    $builder->add($index, CheckboxType::class, [
                        'mapped' => false,
                        'required' => false,
                        'data' => in_array($revision->getAlias(), array_keys($info['aliases'])),
                    ]);
                }
	}
        
        /**
         * {@inheritdoc}
         */
        public function buildView(FormView $view, FormInterface $form, array $options)
        {
            parent::buildView($view, $form, $options);
            
            $view->vars['indexes'] = array_keys($options['indexes']);
        }

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) 
        {
            parent::configureOptions($resolver);
            
            $resolver->setDefaults([
                'type' => null, 
                'indexes' => []
            ]);
	}
}
