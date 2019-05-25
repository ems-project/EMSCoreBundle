<?php

namespace EMS\CoreBundle\Form\View;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * An export view plugin
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class ExportViewType extends ViewType
{
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel()
    {
        return "Export: perform an elasticsearch query and generate a export with a twig template";
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getName()
    {
        return "Export";
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
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
                'label' => 'The Access-Control-Allow-Originm header',
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
        ->add('disposition', ChoiceType::class, [
                'label' => 'File diposition',
                'expanded' => true,
                'attr' => [
                ],
                'choices' => [
                        'None' => null,
                        'Attachment' => 'attachment',
                        'Inline' => 'inline',
                ]
        ])
        ->add('export_type', ChoiceType::class, [
                'label' => 'Export type',
                'expanded' => false,
                'attr' => [
                ],
                'choices' => [
                        'Raw (HTML, XML, JSON, ...)' => null,
                        'PDF (dompdf)' => 'dompdf',
                ]
        ])
        ->add('pdf_orientation', ChoiceType::class, [
            'required' => false,
            'choices' => [
                'Portrait' => 'portrait',
                'Landscape' => 'landscape',
            ],
        ])
        ->add('pdf_size', ChoiceType::class, [
            'required' => false,
            'choices' => array_combine(array_keys(CPDF::$PAPER_SIZES), array_keys(CPDF::$PAPER_SIZES)),
        ]);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getBlockPrefix()
    {
        return 'export_view';
    }
    
    
    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\View\ViewType::generateResponse()
     */
    public function generateResponse(View $view, Request $request)
    {
        $parameters = $this->getParameters($view, $this->formFactory, $request);

        if (isset($view->getOptions()['export_type']) or $view->getOptions()['export_type'] === 'dompdf') {
            // instantiate and use the dompdf class
            $dompdf = new Dompdf();
            $dompdf->loadHtml($parameters['render']);

            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper(
                (isset($view->getOptions()['pdf_size']) and $view->getOptions()['pdf_size']) ? $view->getOptions()['pdf_size'] : 'A4',
                (isset($view->getOptions()['pdf_orientation']) and $view->getOptions()['pdf_orientation']) ? $view->getOptions()['pdf_orientation'] : 'portrait'
            );

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            $dompdf->stream($parameters['filename'] ?? "document.pdf", [
                'compress' => 1,
                'Attachment' => ( isset($view->getOptions()['disposition'])  && $view->getOptions()['disposition']  === 'attachment')?1:0,
            ]);
            exit;
        }

        $response = new Response();

        if (!empty($view->getOptions()['disposition'])) {
            $attachment = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
            if ($view->getOptions()['disposition'] == 'inline') {
                $attachment = ResponseHeaderBag::DISPOSITION_INLINE;
            }
            $disposition = $response->headers->makeDisposition($attachment, $parameters['filename']);
            $response->headers->set('Content-Disposition', $disposition);
        }
        
        $response->headers->set('Content-Type', $parameters['mimetype']);
        if ($parameters['allow_origin']) {
            $response->headers->set('Access-Control-Allow-Origin', $parameters['allow_origin']);
        }

        $response->setContent($parameters['render']);
        
        return $response;
    }
    
    public function getParameters(View $view, FormFactoryInterface $formFactoty, Request $request)
    {
        
        try {
            $renderQuery = $this->twig->createTemplate($view->getOptions()['body'])->render([
                    'view' => $view,
                    'contentType' => $view->getContentType(),
                    'environment' => $view->getContentType()->getEnvironment(),
            ]);
        } catch (\Throwable $e) {
            $renderQuery = "{}";
        }
        
        $searchQuery = [
                'index' => $view->getContentType()->getEnvironment()->getAlias(),
                'type' => $view->getContentType()->getName(),
                'body' => $renderQuery,
        ];
        
        if (isset($view->getOptions()['size'])) {
            $searchQuery['size'] = $view->getOptions()['size'];
        }
        
        $result = $this->client->search($searchQuery);
        
        try {
            $render = $this->twig->createTemplate($view->getOptions()['template'])->render([
                    'view' => $view,
                    'contentType' => $view->getContentType(),
                    'environment' => $view->getContentType()->getEnvironment(),
                    'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $render = "Something went wrong with the template of the view ".$view->getName()." for the content type ".$view->getContentType()->getName()." (".$e->getMessage().")";
        }
        
        try {
            $filename = $this->twig->createTemplate($view->getOptions()['filename'])->render([
                    'view' => $view,
                    'contentType' => $view->getContentType(),
                    'environment' => $view->getContentType()->getEnvironment(),
                    'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $filename = "Something went wrong with the template of the view ".$view->getName()." for the content type ".$view->getContentType()->getName()." (".$e->getMessage().")";
        }
        
        return [
                'render' => $render,
                'filename' => $filename,
                'mimetype' => empty($view->getOptions()['mimetype'])?'application/bin':$view->getOptions()['mimetype'],
                'allow_origin' => empty($view->getOptions()['allow_origin'])?null:$view->getOptions()['allow_origin'],
                'view' => $view,
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
        ];
    }
}
