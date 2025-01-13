<?php

declare(strict_types=1);

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

/**
 * @extends AbstractType<mixed>
 */
abstract class ViewType extends AbstractType
{
    public function __construct(
        protected FormFactory $formFactory,
        protected Environment $twig,
        protected LoggerInterface $logger,
        private readonly string $templateNamespace
    ) {
    }

    abstract public function getLabel(): string;

    abstract public function getName(): string;

    /**
     * @return array<mixed>
     */
    abstract public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array;

    #[\Override]
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
        $response->setContent($this->twig->render("@$this->templateNamespace/view/custom/".$this->getBlockPrefix().'.html.twig', $parameters));

        return $response;
    }
}
