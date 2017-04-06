<?php

namespace EMS\CoreBundle\Form\DataField;


use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CodeFieldType extends DataFieldType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Code editor field';
	}
	
	/**
	 * Get a icon to visually identify a FieldType
	 * 
	 * @return string
	 */
	public static function getIcon(){
		return 'fa fa-code';
	}
	
	/**
	 *
	 * @param FormBuilderInterface $builder        	
	 * @param array $options        	
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		/** @var FieldType $fieldType */
		$fieldType = $builder->getOptions () ['metadata'];
		$builder->add ( 'text_value', HiddenType::class, [ 
				'attr' => [ 
						'class' => 'code_editor_ems',
						'data-max-lines' => $options['maxLines'],
						'data-language' => $options['language'],
						'data-height' => $options['height'],
						'data-theme' => $options['theme'],
						'data-disabled' => !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
				],
				'label' => $options['label'],
				'required' => false,
 				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
		] );
	}
	
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'codefieldtype';
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
		$resolver->setDefault('language', null);
		$resolver->setDefault('theme', null);
		$resolver->setDefault('maxLines', 15);
		$resolver->setDefault('height', false);
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
		$optionsForm->get ( 'displayOptions' )->add ( 'icon', IconPickerType::class, [
				'required' => false
		] )->add ( 'maxLines', IntegerType::class, [
				'required' => false,
		])->add ( 'height', IntegerType::class, [
				'required' => false,
		])->add ( 'language', TextType::class, [
				'required' => false,
				'attr' => [
						'class' => 'code_editor_mode_ems',
				],
		])->add ( 'theme', TextType::class, [
				'required' => false,
				'attr' => [
						'class' => 'code_editor_theme_ems',
				],
		]);
	}
}