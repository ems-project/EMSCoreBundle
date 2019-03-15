<?php

namespace EMS\CoreBundle\Form\View;

use Elasticsearch\Client;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\View\ViewType;
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
        $response = new Response();
        $parameters = $this->getParameters($view, $this->formFactory, $request);
        
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
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getParameters(View $view, FormFactoryInterface $formFactoty, Request $request)
    {
        
        try {
            $renderQuery = $this->twig->createTemplate($view->getOptions()['body'])->render([
                    'view' => $view,
                    'contentType' => $view->getContentType(),
                    'environment' => $view->getContentType()->getEnvironment(),
            ]);
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            $render = "Something went wrong with the template of the view ".$view->getName()." for the content type ".$view->getContentType()->getName()." (".$e->getMessage().")";
        }
        
        try {
            $filename = $this->twig->createTemplate($view->getOptions()['filename'])->render([
                    'view' => $view,
                    'contentType' => $view->getContentType(),
                    'environment' => $view->getContentType()->getEnvironment(),
                    'result' => $result,
            ]);
        } catch (\Exception $e) {
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
