<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\View;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use \Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactory;
use Elasticsearch\Client;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
abstract class ViewType extends AbstractType {
	
	
	/**@var \Twig_Environment $twig*/
	protected $twig;
	/** @var Client $client */
	protected $client;
	/**@var FormFactory*/
	protected $formFactory;
	
	public function __construct($formFactory, $twig, $client){
		$this->twig = $twig;
		$this->client = $client;
		$this->formFactory = $formFactory;
	}
	
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
		$resolver->setDefaults ( array (
				'view' => null,
				'label' => $this->getName().' options',
		) );
	}
	
	/**
	 * Generate a response for a view
	 * 
	 * @param View $view
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function generateResponse(View $view, Request $request) {
		$response = new Response();
		$parameters = $this->getParameters($view, $this->formFactory, $request);
		$response->setContent($this->twig->render('@EMSCore/view/custom/'.$this->getBlockPrefix().'.html.twig', $parameters));
		return $response;
	}
	
	
}