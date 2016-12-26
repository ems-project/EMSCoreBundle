<?php

namespace Ems\CoreBundle\Form\Form;

use Ems\CoreBundle\Entity\ContentType;
use Ems\CoreBundle\Form\Field\ColorPickerType;
use Ems\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use Ems\CoreBundle\Form\Field\IconPickerType;
use Ems\CoreBundle\Form\Field\RolePickerType;
use Ems\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeType extends AbstractType {
	/**
	 *
	 * @param FormBuilderInterface $builder        	
	 * @param array $options        	
	 */
    public function buildForm(FormBuilderInterface $builder, array $options) {

    	/** @var ContentType $contentType */
		$contentType = $builder->getData ();
    	
    	if(!empty($options['mapping']) && !empty(array_values($options['mapping'])[0]['mappings'][$options['data']->getName()]['properties'])) {
	    	$mapping = array_values($options['mapping'])[0]['mappings'][$options['data']->getName()]['properties'];
			$builder->add ( 'labelField', ContentTypeFieldPickerType::class, [
				'required' => false,
				'firstLevelOnly' => true,
				'mapping' => $mapping,
				'types' => [
						'string',
						'integer'
			]]);
    		
			$builder->add ( 'colorField', ContentTypeFieldPickerType::class, [
				'required' => false,
				'firstLevelOnly' => true,
				'mapping' => $mapping,
				'types' => [
						'string',
			]]);
			$builder->add ( 'circlesField', ContentTypeFieldPickerType::class, [
				'required' => false,
				'firstLevelOnly' => true,
				'mapping' => $mapping,
				'types' => [
						'string',
			]]);
			$builder->add ( 'emailField', ContentTypeFieldPickerType::class, [
				'required' => false,
				'firstLevelOnly' => true,
				'mapping' => $mapping,
				'types' => [
						'string',
			]]);
			$builder->add ( 'categoryField', ContentTypeFieldPickerType::class, [
				'required' => false,
				'firstLevelOnly' => true,
				'mapping' => $mapping,
				'types' => [
						'string',
			]]);
			$builder->add ( 'imageField', ContentTypeFieldPickerType::class, [
				'required' => false,
				'firstLevelOnly' => true,
				'mapping' => $mapping,
				'types' => [
						'nested',
			]]);
			$builder->add ( 'assetField', ContentTypeFieldPickerType::class, [
				'required' => false,
				'firstLevelOnly' => true,
				'mapping' => $mapping,
				'types' => [
						'nested',
			]]);
			$builder->add ( 'sortBy', ContentTypeFieldPickerType::class, [
				'required' => false,
				'firstLevelOnly' => false,
				'mapping' => $mapping,
				'types' => [
						'string',
						'integer',
						'text',
						'keyword'
			]]);
    	}
    	
		

// 		$builder->add ( 'parentField');
// 		$builder->add ( 'userField');
// 		$builder->add ( 'dateField');
// 		$builder->add ( 'startDateField');
		$builder->add ( 'refererFieldName');
		$builder->add ( 'editTwigWithWysiwyg', CheckboxType::class, [
			'label' => 'Edit the Twig template with a WYSIWYG editor',
			'required' => false,
		]);
		$builder->add ( 'singularName', TextType::class);
		$builder->add ( 'pluralName', TextType::class);
		$builder->add ( 'icon', IconPickerType::class, [
			'required' => false,
		]);
		$builder->add ( 'color', ColorPickerType::class, [
			'required' => false,
		]);
		
		
		$builder->add ( 'description', TextareaType::class, [
				'required' => false,
				'attr' => [
						'class' => 'ckeditor'
				]
		] );
		$builder->add ( 'indexTwig', TextareaType::class, [
				'required' => false,
				'attr' => [
						'class' => $options['twigWithWysiwyg']?'ckeditor':'',
						'rows' => 10,
				]
		] );
		$builder->add ( 'extra', TextareaType::class, [
				'required' => false,
				'attr' => [
						'rows' => 10,
				]
		] );
		
		
		$builder->add ( 'save', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save'
		] );		
		$builder->add ( 'saveAndUpdateMapping', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save'
		] );		
		$builder->add ( 'saveAndClose', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save'
		] );		

		$builder->add ( 'rootContentType');
		
		if($contentType->getEnvironment()->getManaged()){
			$builder->add ( 'askForOuuid', CheckboxType::class, [
				'label' => 'Ask for OUUID',
				'required' => false,
			]);
			$builder->add ( 'createRole', RolePickerType::class);
			$builder->add ( 'editRole', RolePickerType::class);
			$builder->add ( 'viewRole', RolePickerType::class);
			$builder->add ( 'orderField');
			$builder->add ( 'saveAndEditStructure', SubmitEmsType::class, [ 
					'attr' => [ 
							'class' => 'btn-primary btn-sm ' 
					],
					'icon' => 'fa fa-save'
			] );
		}
		
		return parent::buildForm($builder, $options);
		 
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefault ( 'twigWithWysiwyg', true );
		$resolver->setDefault ( 'mapping', null );
	}
	
}
