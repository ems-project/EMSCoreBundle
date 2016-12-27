<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\DataField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Entity\View;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
abstract class ViewType extends AbstractType {

	/**
	 * Get a small description
	 *
	 * @return string
	 */
	abstract public function getLabel();
	
	/**
	 * Get a better name than the class path
	 * 
	 * @return string
	 */
	abstract public function getName();
	
	/**
	 * Get arguments that should passed to the associated twig template
	 * 
	 * @return array
	 */
	abstract public function getParameters(View $view, FormFactoryInterface $formFactoty, Request $request);

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefault ( 'label', $this->getName().' options');
	}
	
	
}