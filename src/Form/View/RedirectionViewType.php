<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectionViewType extends ViewType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Redirection: add a redirection link in the content type\'s sub-menu';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Redirection';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder->add('template', CodeEditorType::class, [
            'label' => 'Template',
            'attr' => [],
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'redirection';
    }

    #[\Override]
    public function generateResponse(View $view, Request $request): Response
    {
        $url = $this->twig->createTemplate($view->getOptions()['template'] ?? '')->render([
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
        ]);

        return new RedirectResponse($url);
    }

    #[\Override]
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        return [];
    }
}
