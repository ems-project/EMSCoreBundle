<?php

namespace EMS\CoreBundle\Form\View;

use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use EMS\CoreBundle\Form\Nature\ReorderType;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Throwable;
use Twig_Environment;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class SorterViewType extends ViewType
{
    
    /**@var Session $session*/
    protected $session;
    /**@var DataService */
    protected $dataService;
    /**@var Router */
    protected $router;
    
    public function __construct(FormFactory $formFactory, Twig_Environment $twig, Client $client, LoggerInterface $logger, Session $session, DataService $dataService, Router $router)
    {
        parent::__construct($formFactory, $twig, $client, $logger);
        $this->session = $session;
        $this->dataService = $dataService;
        $this->router = $router;
    }

    public function getLabel()
    {
        return "Sorter: order a sub set (based on a ES query)";
    }
    
    public function getName()
    {
        return "Sorter";
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        
        /**@var View $view */
        $view = $options['view'];
        
        $mapping = $this->client->indices()->getMapping([
                'index' => $view->getContentType()->getEnvironment()->getAlias(),
                'type' => $view->getContentType()->getName()
        ]);
        
        $mapping = array_values($mapping)[0]['mappings'][$view->getContentType()->getName()]['properties'];
        
        
        $builder
        ->add('body', CodeEditorType::class, [
                'label' => 'The Elasticsearch body query [JSON Twig]',
                'attr' => [
                ],
                'slug' => 'sorter_query',
        ])
        ->add('size', IntegerType::class, [
                'label' => 'Limit the result to the x first results',
        ])
        ->add('field', ContentTypeFieldPickerType::class, [
                'label' => 'Target order field (integer)',
                'required' => false,
                'firstLevelOnly' => false,
                'mapping' => $mapping,
                'types' => [
                    'integer',
                    'long',
                ]]);
    }
    
    public function getBlockPrefix()
    {
        return 'sorter_view';
    }
    

    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request)
    {
        
        return [];
    }
    
    public function generateResponse(View $view, Request $request)
    {
        
        try {
            $renderQuery = $this->twig->createTemplate($view->getOptions()['body'])->render([
                    'view' => $view,
                    'contentType' => $view->getContentType(),
                    'environment' => $view->getContentType()->getEnvironment(),
            ]);
        } catch (Throwable $e) {
            $renderQuery = "{}";
        }
        
        $boby = json_decode($renderQuery, true);
        
        $boby['sort'] = [
                $view->getOptions()['field'] => [
                        'order' => 'asc',
                        "missing" => "_last",
                ]
        ];
        
        $searchQuery = [
                'index' => $view->getContentType()->getEnvironment()->getAlias(),
                'type' => $view->getContentType()->getName(),
                'body' => $boby,
        ];
        
        $searchQuery['size'] = 100;
        if (isset($view->getOptions()['size'])) {
            $searchQuery['size'] = $view->getOptions()['size'];
        }
        
        $result = $this->client->search($searchQuery);
        
        if ($result['hits']['total'] > $searchQuery['size']) {
            $this->logger->warning('form.view.sorter.too_many_documents', [
                'total' => $result['hits']['total'],
            ]);
        }
        
        $data = [];
        
        $form = $this->formFactory->create(ReorderType::class, $data, [
                'result' => $result,
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            $counter = 1;
            foreach ($request->request->get('reorder')['items'] as $itemKey => $value) {
                try {
                    $revision = $this->dataService->initNewDraft($view->getContentType()->getName(), $itemKey);
                    $data = $revision->getRawData();
                    $data[$view->getOptions()['field']] = $counter++;
                    $revision->setRawData($data);
                    $this->dataService->finalizeDraft($revision);
                } catch (Throwable $e) {
                    $this->logger->warning('form.view.sorter.error_with_document', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $view->getContentType()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $itemKey,
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                    ]);
                }
            }
            $this->logger->notice('form.view.hierarchical.ordered', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $view->getContentType()->getName(),
                'view_name' => $view->getName(),
            ]);

            return new RedirectResponse($this->router->generate('data.draft_in_progress', [
                    'contentTypeId' => $view->getContentType()->getId(),
            ], UrlGeneratorInterface::RELATIVE_PATH));
//             return $this->redirectToRoute();
        }
        
        
        $response = new Response();
        $response->setContent($this->twig->render('@EMSCore/view/custom/' . $this->getBlockPrefix() . '.html.twig', [
                'result' => $result,
                'view' => $view,
                'form' => $form->createView(),
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
        ]));
        return $response;
    }
}
