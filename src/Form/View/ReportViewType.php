<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class ReportViewType extends ViewType
{
    public function __construct(FormFactory $formFactory, Environment $twig, private readonly ElasticaService $elasticaService, LoggerInterface $logger, string $templateNamespace)
    {
        parent::__construct($formFactory, $twig, $logger, $templateNamespace);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Report: perform an elasticsearch query and generate a report with a twig template';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Report';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder
        ->add('body', CodeEditorType::class, [
            'label' => 'The Elasticsearch body query [JSON Twig]',
            'attr' => [
            ],
            'slug' => 'report_query',
        ])
        ->add('size', IntegerType::class, [
            'label' => 'Limit the result to the x first results',
        ])
        ->add('template', CodeEditorType::class, [
            'label' => 'The Twig template used to display each keywords',
            'attr' => [
            ],
            'slug' => 'report_template',
        ])
        ->add('header', CodeEditorType::class, [
            'label' => 'The HTML template included at the end of the header',
            'attr' => [
            ],
        ])
        ->add('javascript', CodeEditorType::class, [
            'label' => 'The HTML template included at the end of the page (after jquery and bootstrap)',
            'attr' => [
            ],
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'report_view';
    }

    #[\Override]
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        try {
            $renderQuery = $this->twig->createTemplate($view->getOptions()['body'] ?? '')->render([
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['view' => $view->getName(), 'option' => 'body']);
            $renderQuery = '{}';
        }

        $searchQuery = [
            'index' => $view->getContentType()->giveEnvironment()->getAlias(),
            'type' => $view->getContentType()->getName(),
            'body' => $renderQuery,
        ];

        if (isset($view->getOptions()['size'])) {
            $searchQuery['size'] = $view->getOptions()['size'];
        }

        $search = $this->elasticaService->convertElasticsearchSearch($searchQuery);
        $resultSet = $this->elasticaService->search($search);

        try {
            $render = $this->twig->createTemplate($view->getOptions()['template'] ?? '')->render([
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
                'result' => $resultSet->getResponse()->getData(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['view' => $view->getName(), 'option' => 'template']);
            $render = 'Something went wrong with the template of the view '.$view->getLabel().' for the content type '.$view->getContentType()->getName().' ('.$e->getMessage().')';
        }
        try {
            $javascript = $this->twig->createTemplate($view->getOptions()['javascript'] ?? '')->render([
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
                'result' => $resultSet->getResponse()->getData(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['view' => $view->getName(), 'option' => 'javascript']);
            $javascript = '';
        }
        try {
            $header = $this->twig->createTemplate($view->getOptions()['header'] ?? '')->render([
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
                'result' => $resultSet->getResponse()->getData(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['view' => $view->getName(), 'option' => 'header']);
            $header = '';
        }

        return [
            'render' => $render,
            'header' => $header,
            'javascript' => $javascript,
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
        ];
    }
}
