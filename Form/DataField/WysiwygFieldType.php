<?php

namespace Ems\CoreBundle\Form\DataField;


use Ems\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType as TextareaSymfonyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class WysiwygFieldType extends DataFieldType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'WYSIWYG field';
	}
	
	/**
	 * Get a icon to visually identify a FieldType
	 * 
	 * @return string
	 */
	public static function getIcon(){
		return 'fa fa-newspaper-o';
	}
	
	/**
	 *
	 * @param FormBuilderInterface $builder        	
	 * @param array $options        	
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		/** @var FieldType $fieldType */
		$fieldType = $builder->getOptions () ['metadata'];
		$builder->add ( 'text_value', TextareaSymfonyType::class, [ 
				'attr' => [ 
						'class' => 'ckeditor_ems',
						'data-height' => $options['height'],
				],
				'label' => $options['label'],
				'required' => false,
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildView(FormView $view, FormInterface $form, array $options) {
		/*get options for twig context*/
		parent::buildView($view, $form, $options);
		$view->vars ['icon'] = $options ['icon'];
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		/*set the default option value for this kind of compound field*/
		parent::configureOptions($resolver);
		$resolver->setDefault('icon', null);
		$resolver->setDefault('height', 400);
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
		parent::buildOptionsForm ( $builder, $options );
		$optionsForm = $builder->get ( 'options' );
		
		// String specific mapping options
		$optionsForm->get ( 'mappingOptions' )->add ( 'analyzer', AnalyzerPickerType::class);
		$optionsForm->get ( 'displayOptions' )->add ( 'height', IntegerType::class, [
				'required' => false,
		]);
	}
}