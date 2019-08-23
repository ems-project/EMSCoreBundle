<?php

namespace EMS\CoreBundle\Form\View;

use Elasticsearch\Client;
use EMS\CoreBundle\Entity\View;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig_Environment;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
abstract class ViewType extends AbstractType
{
    
    
    /**@var Twig_Environment $twig*/
    protected $twig;
    /** @var Client $client */
    protected $client;
    /**@var FormFactory*/
    protected $formFactory;
    /**@var LoggerInterface*/
    protected $logger;
    
    public function __construct(FormFactory $formFactory, Twig_Environment $twig, Client $client, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->client = $client;
        $this->formFactory = $formFactory;
        $this->logger = $logger;
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
     * @param View $view
     * @param FormFactoryInterface $formFactory
     * @param Request $request
     * @return mixed
     */
    abstract public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request);
    
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array (
                'view' => null,
                'label' => $this->getName() . ' options',
        ));
    }

    /**
     * Generate a response for a view
     * @param View $view
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function generateResponse(View $view, Request $request)
    {
        $response = new Response();
        $parameters = $this->getParameters($view, $this->formFactory, $request);
        $response->setContent($this->twig->render('@EMSCore/view/custom/' . $this->getBlockPrefix() . '.html.twig', $parameters));
        return $response;
    }
}
