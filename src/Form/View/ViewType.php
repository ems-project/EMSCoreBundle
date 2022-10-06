<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\View;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

abstract class ViewType extends AbstractType
{
    protected Environment $twig;
    protected FormFactory $formFactory;
    protected LoggerInterface $logger;

    public function __construct(FormFactory $formFactory, Environment $twig, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->logger = $logger;
    }

    abstract public function getLabel(): string;

    abstract public function getName(): string;

    /**
     * @return array<mixed>
     */
    abstract public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
                'view' => null,
                'label' => $this->getName().' options',
        ]);
    }

    public function generateResponse(View $view, Request $request): Response
    {
        $response = new Response();
        $parameters = $this->getParameters($view, $this->formFactory, $request);
        $response->setContent($this->twig->render('@EMSCore/view/custom/'.$this->getBlockPrefix().'.html.twig', $parameters));

        return $response;
    }
}
