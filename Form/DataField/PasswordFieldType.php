<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
							
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
 class PasswordFieldType extends DataFieldType {
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Password field';
	}
	
	/**
	 * Get a icon to visually identify a FieldType
	 * 
	 * @return string
	 */
	public static function getIcon(){
		return 'glyphicon glyphicon-asterisk';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		/** @var FieldType $fieldType */
		$fieldType = $options ['metadata'];
		$builder->add ( 'password_value', PasswordType::class, [
				'label' => (null != $options ['label']?$options ['label']:$fieldType->getName()),
				'disabled'=> $this->isDisabled($options),
				'required' => false,
				'attr' => [
						'autocomplete' => "new-password" //http://stackoverflow.com/questions/18531437/stop-google-chrome-auto-fill-the-input
				],
		] );		
		
		$builder->add ( 'reset_password_value', CheckboxType::class, [
				'label' => 'Reset the password',
				'disabled'=> $this->isDisabled($options),
				'required' => false,
		] );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );
		$resolver->setDefault ( 'encryption', null );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
		parent::buildOptionsForm ( $builder, $options );
		$optionsForm = $builder->get ( 'options' );
		
		// String specific display options
		$optionsForm->get ( 'displayOptions' )->add ( 'encryption', ChoiceType::class, [ 
				'required' => false,
				'choices' => [ 
					'sha1' => 'sha1',
					'md5' => 'md5',
				], 
				'empty_data'  => 'sha1',
		] );
		
		// String specific mapping options
		$optionsForm->get ( 'mappingOptions' )->add ( 'index', ChoiceType::class, [ 
				'required' => false,
				'choices' => [ 
					'No' => 'no',
				], 
				'empty_data'  => 'no',
		] );
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType ()->getDeleted ()) {
			switch ($data->getFieldType ()->getDisplayOptions()['encryption']){
				case 'md5':
					$out [$data->getFieldType ()->getName ()] = md5($data->getTextValue ());
					break;
				default:
					$out [$data->getFieldType ()->getName ()] = sha1($data->getTextValue ());
					break;
			}

		}
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getDefaultOptions($name) {
		$out = parent::getDefaultOptions($name);
		
		$out['mappingOptions']['index'] = 'not_analyzed';
	
		return $out;
	}
}