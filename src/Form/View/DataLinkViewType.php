<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\View;

use EMS\CommonBundle\Service\ElasticaService;
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
    private ElasticaService $elasticaService;

    public function __construct(FormFactory $formFactory, Environment $twig, ElasticaService $elasticaService, LoggerInterface $logger)
    {
        parent::__construct($formFactory, $twig, $logger);
        $this->elasticaService = $elasticaService;
    }

    public function getLabel(): string
    {
        return 'Data Link: manipulate the choices in a data link of this content type';
    }

    public function getName(): string
    {
        return 'Data Link';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<mixed>                               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder->add('template', CodeEditorType::class, [
            'label' => 'Template',
            'attr' => [],
            'slug' => 'data_link_template',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'data_link';
    }

    public function render(View $view, DataLinks $dataLinks): void
    {
        $this->twig->createTemplate($view->getOptions()['template'])->render([
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
            'dataLinks' => $dataLinks,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        return [];
    }
}
