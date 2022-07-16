<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CommonBundle\Elasticsearch\Response\Response as EmsResponse;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use EMS\CoreBundle\Form\Nature\ReorderType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Mapping;
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
use Symfony\Component\Routing\RouterInterface;
use Throwable;
use Twig\Environment;

class SorterViewType extends ViewType
{
    /** @var int */
    public const SEARCH_SIZE = 100;
    /** @var Session */
    protected $session;
    /** @var DataService */
    protected $dataService;
    /** @var RouterInterface */
    protected $router;
    /** @var Mapping */
    protected $mapping;
    /** @var ElasticaService */
    private $elasticaService;

    public function __construct(FormFactory $formFactory, Environment $twig, Mapping $mapping, ElasticaService $elasticaService, LoggerInterface $logger, Session $session, DataService $dataService, RouterInterface $router)
    {
        parent::__construct($formFactory, $twig, $logger);
        $this->mapping = $mapping;
        $this->session = $session;
        $this->dataService = $dataService;
        $this->router = $router;
        $this->elasticaService = $elasticaService;
    }

    public function getLabel(): string
    {
        return 'Sorter: order a sub set (based on a ES query)';
    }

    public function getName(): string
    {
        return 'Sorter';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var View $view */
        $view = $options['view'];
        $mapping = [];

        if (null !== $environment = $view->getContentType()->getEnvironment()) {
            $mapping = $this->mapping->getMapping([$environment->getName()]);
        }

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
                ], ]);
    }

    public function getBlockPrefix(): string
    {
        return 'sorter_view';
    }

    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        return [];
    }

    public function generateResponse(View $view, Request $request): Response
    {
        try {
            $renderQuery = $this->twig->createTemplate($view->getOptions()['body'])->render([
                    'view' => $view,
                    'contentType' => $view->getContentType(),
                    'environment' => $view->getContentType()->getEnvironment(),
            ]);
        } catch (Throwable $e) {
            $renderQuery = '{}';
        }

        $boby = \json_decode($renderQuery, true);

        $boby['sort'] = [
                $view->getOptions()['field'] => [
                        'order' => 'asc',
                        'missing' => '_last',
                ],
        ];

        $searchQuery = [
                'index' => $view->getContentType()->getEnvironment()->getAlias(),
                'type' => $view->getContentType()->getName(),
                'body' => $boby,
        ];

        $searchQuery['size'] = self::SEARCH_SIZE;
        if (isset($view->getOptions()['size'])) {
            $searchQuery['size'] = $view->getOptions()['size'];
        }

        $search = $this->elasticaService->convertElasticsearchSearch($searchQuery);
        $resultSet = $this->elasticaService->search($search);
        $emsResponse = EmsResponse::fromResultSet($resultSet);

        if ($emsResponse->getTotal() > self::SEARCH_SIZE) {
            $this->logger->warning('form.view.sorter.too_many_documents', [
                'total' => $emsResponse->getTotal(),
            ]);
        }

        $data = [];

        $form = $this->formFactory->create(ReorderType::class, $data, [
            'result' => $resultSet->getResponse()->getData(),
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
                'view_label' => $view->getLabel(),
            ]);

            return new RedirectResponse($this->router->generate('data.draft_in_progress', [
                    'contentTypeId' => $view->getContentType()->getId(),
            ], UrlGeneratorInterface::RELATIVE_PATH));
        }

        $response = new Response();
        $response->setContent($this->twig->render('@EMSCore/view/custom/'.$this->getBlockPrefix().'.html.twig', [
                'response' => $emsResponse,
                'view' => $view,
                'form' => $form->createView(),
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
        ]));

        return $response;
    }
}
