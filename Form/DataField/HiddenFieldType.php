<?php

namespace Ems\CoreBundle\Form\DataField;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
				
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
 class HiddenFieldType extends DataFieldType {
	

 	/**
 	 *
 	 * {@inheritdoc}
 	 *
 	 */
 	public function getLabel(){
 		throw new \Exception('This Field Type should not used as field (as service)');
 	}
 	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
			$builder->add ( 'encodedText', HiddenType::class);		
	}

}