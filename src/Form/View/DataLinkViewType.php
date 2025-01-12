<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Core\Document\DataLinks;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class DataLinkViewType extends ViewType
{
    public function __construct(FormFactory $formFactory, Environment $twig, LoggerInterface $logger, string $templateNamespace)
    {
        parent::__construct($formFactory, $twig, $logger, $templateNamespace);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Data Link: manipulate the choices in a data link of this content type';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Data Link';
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
            'slug' => 'data_link_template',
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'data_link';
    }

    public function render(View $view, DataLinks $dataLinks): void
    {
        $this->twig->createTemplate($view->getOptions()['template'] ?? '')->render([
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
            'dataLinks' => $dataLinks,
        ]);
    }

    #[\Override]
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        return [];
    }
}
