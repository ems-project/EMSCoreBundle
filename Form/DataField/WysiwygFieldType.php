<?php

namespace EMS\CoreBundle\Form\DataField;


use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType as TextareaSymfonyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use EMS\CoreBundle\Entity\DataField;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use EMS\CoreBundle\Form\DataTransformer\DataFieldTransformer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use EMS\CoreBundle\Entity\FieldType;

class WysiwygFieldType extends DataFieldType {
	
	/**@var RouterInterface*/ 
	private $router;
	
	
	
	public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, RouterInterface $router) {
		parent::__construct($authorizationChecker, $formRegistry);
		$this->router= $router;
	}
	
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
	 * {@inheritDoc}
	 * @see \Symfony\Component\Form\AbstractType::getParent()
	 */
	public function getParent() {
		return TextareaSymfonyType::class;
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildView(FormView $view, FormInterface $form, array $options) {
		/*get options for twig context*/
		parent::buildView($view, $form, $options);
		$view->vars ['icon'] = $options ['icon'];
		$attr = $view->vars['attr'];
		if(empty($attr['class'])){
			$attr['class'] = '';
			$attr['data-height'] = $options['height'];
		}
		
		$attr['class'] .= ' ckeditor_ems';
		$view->vars ['attr'] = $attr;
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
	 * {@inheritDoc}
	 * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
	 */
	public function reverseViewTransform($data, FieldType $fieldType){
		
		$path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH );
		
		$out= preg_replace_callback(
			'/('.preg_quote(substr($path, 0, strlen($path)-8), '/').')([^\n\r"\'\?]*)/i',
			function ($matches){
				return 'ems://asset:'.$matches[2];
			},
			$data
		); 
		return parent::reverseViewTransform($out, $fieldType);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
	 */
	public function viewTransform(DataField $data){
		$out = parent::viewTransform($data);
		
		$path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH );
		$path = substr($path, 0, strlen($path)-8);
		$out= preg_replace_callback(
			'/(ems:\/\/asset:)([^\n\r"\'\?]*)/i',
			function ($matches) use ($path) {
				return $path.$matches[2];
			},
			$out
		);
		return $out;
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
		$optionsForm->get ( 'mappingOptions' )
		->add ( 'analyzer', AnalyzerPickerType::class)
		->add ( 'copy_to', TextType::class, [
				'required' => false,
		] );
		$optionsForm->get ( 'displayOptions' )->add ( 'height', IntegerType::class, [
				'required' => false,
		]);
	}
	
// 	/**
// 	 *
// 	 * {@inheritdoc}
// 	 *
// 	 */
// 	public function getDataValue(DataField &$dataValues, array $options){
		
// 		if(is_array($dataValues->getRawData()) && count($dataValues->getRawData()) === 0){
// 			return null; //empty array means null/empty
// 		}
		
// 		if($dataValues->getRawData()!== null && !is_string($dataValues->getRawData())){
// 			if(is_array($dataValues->getRawData()) && count($dataValues->getRawData()) == 1 && is_string($dataValues->getRawData()[0])) {
// 				$this->addMessage('String expected, single string in array instead');
// 				return $dataValues->getRawData()[0];
// 			}
// 			$this->addMessage('String expected from the DB: '.print_r($dataValues->getRawData(), true));
// 		}
		
// 		$output = $dataValues->getRawData();
// // 		dump($dataValues->getRawData());
// 		dump($this);
// 		throw new \Exception();
// 		$this->router->generate('ems_file_download', [
// 				'sha1' => 	'__toot__',
// 		]);
		
// 		return $output;
// 	}
	
// 	/**
// 	 *
// 	 * {@inheritdoc}
// 	 *
// 	 */
// 	public function setDataValue($input, DataField &$dataValues, array $options){
// 		if($input!== null && !is_string($input)){
// 			throw new DataFormatException('String expected: '.print_r($rawData, true));
// 		}
// 		$dataValues->setRawData($input);
// 		return $this;
// 	}
}