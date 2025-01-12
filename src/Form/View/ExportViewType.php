<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Service\Pdf\DomPdfPrinter;
use EMS\CommonBundle\Service\Pdf\Pdf;
use EMS\CommonBundle\Service\Pdf\PdfPrintOptions;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\FileDispositionType;
use EMS\CoreBundle\Form\Field\OrientationType;
use EMS\CoreBundle\Form\Field\PdfSizeType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Twig\Environment;

class ExportViewType extends ViewType
{
    public function __construct(FormFactory $formFactory, Environment $twig, private readonly ElasticaService $elasticaService, LoggerInterface $logger, private readonly DomPdfPrinter $pdfPrinter, string $templateNamespace)
    {
        parent::__construct($formFactory, $twig, $logger, $templateNamespace);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Export: perform an elasticsearch query and generate a export with a twig template';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Export';
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
            'slug' => 'export_query',
        ])
        ->add('size', IntegerType::class, [
            'label' => 'Limit the result to the x first results',
        ])
        ->add('template', CodeEditorType::class, [
            'label' => 'The Twig template used to display each keywords',
            'attr' => [
            ],
            'slug' => 'export_template',
        ])
        ->add('mimetype', TextType::class, [
            'label' => 'The mimetype used in the response',
            'attr' => [
            ],
        ])
        ->add('allow_origin', TextType::class, [
            'label' => 'The Access-Control-Allow-Origin header',
            'required' => false,
            'attr' => [
            ],
        ])
        ->add('filename', CodeEditorType::class, [
            'label' => 'The Twig template used to generate the export file name',
            'attr' => [
            ],
            'slug' => 'export_filename',
            'min-lines' => 4,
            'max-lines' => 4,
        ])
        ->add('disposition', FileDispositionType::class)
        ->add('export_type', ChoiceType::class, [
            'label' => 'Export type',
            'expanded' => false,
            'attr' => [
            ],
            'choices' => [
                'Raw (HTML, XML, JSON, ...)' => null,
                'PDF (dompdf)' => 'dompdf',
            ],
        ])
        ->add('pdf_orientation', OrientationType::class, [
            'required' => false,
        ])
        ->add('pdf_size', PdfSizeType::class, [
            'required' => false,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'export_view';
    }

    #[\Override]
    public function generateResponse(View $view, Request $request): Response
    {
        $parameters = $this->getParameters($view, $this->formFactory, $request);

        if (isset($view->getOptions()['export_type']) || 'dompdf' === $view->getOptions()['export_type']) {
            $pdf = new Pdf($parameters['filename'] ?? 'document.pdf', $parameters['render'] ?? 'empty template?');
            $pdfOptions = new PdfPrintOptions([
                PdfPrintOptions::SIZE => $view->getOptions()['pdf_size'] ?? 'A4',
                PdfPrintOptions::ORIENTATION => $view->getOptions()['pdf_orientation'] ?? 'portrait',
                PdfPrintOptions::COMPRESS => true,
                PdfPrintOptions::ATTACHMENT => PdfPrintOptions::ATTACHMENT === ($view->getOptions()['disposition'] ?? null),
            ]);

            return $this->pdfPrinter->getStreamedResponse($pdf, $pdfOptions);
        }

        $response = new Response();

        if (!empty($view->getOptions()['disposition'])) {
            $attachment = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
            if ('inline' == $view->getOptions()['disposition']) {
                $attachment = ResponseHeaderBag::DISPOSITION_INLINE;
            }
            $disposition = $response->headers->makeDisposition($attachment, $parameters['filename']);
            $response->headers->set('Content-Disposition', $disposition);
        }

        $response->headers->set('Content-Type', $parameters['mimetype']);
        if ($parameters['allow_origin']) {
            $response->headers->set('Access-Control-Allow-Origin', $parameters['allow_origin']);
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Accept-Language, If-None-Match, If-Modified-Since');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
        }

        $response->setContent($parameters['render']);

        return $response;
    }

    #[\Override]
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        try {
            $renderQuery = $this->twig->createTemplate($view->getOptions()['body'] ?? '')->render([
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->giveEnvironment(),
            ]);
        } catch (\Throwable $e) {
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
                'environment' => $view->getContentType()->giveEnvironment(),
                'result' => $resultSet->getResponse()->getData(),
            ]);
        } catch (\Throwable $e) {
            $render = 'Something went wrong with the template of the view '.$view->getLabel().' for the content type '.$view->getContentType()->getName().' ('.$e->getMessage().')';
        }

        try {
            $filename = $this->twig->createTemplate($view->getOptions()['filename'] ?? '')->render([
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->giveEnvironment(),
                'result' => $resultSet->getResponse()->getData(),
            ]);
        } catch (\Throwable $e) {
            $filename = 'Something went wrong with the template of the view '.$view->getLabel().' for the content type '.$view->getContentType()->getName().' ('.$e->getMessage().')';
        }

        return [
            'render' => $render,
            'filename' => $filename,
            'mimetype' => empty($view->getOptions()['mimetype']) ? 'application/bin' : $view->getOptions()['mimetype'],
            'allow_origin' => empty($view->getOptions()['allow_origin']) ? null : $view->getOptions()['allow_origin'],
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->giveEnvironment(),
        ];
    }
}
