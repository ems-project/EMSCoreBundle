<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use EMS\CoreBundle\Form\Nature\ReorganizeType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Mapping;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class HierarchicalViewType extends ViewType
{
    public function __construct(
        FormFactory $formFactory,
        Environment $twig,
        private readonly SearchService $searchService,
        private readonly Mapping $mapping,
        LoggerInterface $logger,
        protected DataService $dataService,
        protected RouterInterface $router,
        protected ContentTypeService $contentTypeService,
        private readonly string $templateNamespace)
    {
        parent::__construct($formFactory, $twig, $logger, $templateNamespace);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Hierarchical: manage a menu structure (based on a ES query)';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Hierarchical';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        /** @var View $view */
        $view = $options['view'];
        $environment = $view->getContentType()->giveEnvironment();
        $mapping = $this->mapping->getMapping($environment);

        $fieldType = new FieldType();

        $builder
        ->add('parent', DataLinkFieldType::class, [
            'label' => 'Parent',
            'metadata' => $fieldType,
            'type' => $view->getContentType()->getName(),
            'multiple' => false,
            'dynamicLoading' => true,
        ])
        ->add('size', IntegerType::class, [
            'label' => 'Limit the result to the x first results',
            'attr' => [
            ],
        ])
        ->add('maxDepth', IntegerType::class, [
            'label' => 'Limit the menu\'s depth',
            'attr' => [
            ],
        ])
        ->add('maxDepth', IntegerType::class, [
            'label' => 'Limit the menu\'s depth',
            'attr' => [
            ],
        ])
        ->add('field', ContentTypeFieldPickerType::class, [
            'label' => 'Target children field (datalink)',
            'required' => false,
            'firstLevelOnly' => false,
            'mapping' => $mapping,
            'types' => [
                'keyword',
                'text', // TODO: for ES2 support
            ], ]);

        $builder->get('parent')->addModelTransformer(new CallbackTransformer(
            function ($raw) {
                $dataField = new DataField();
                $dataField->setRawData($raw);

                return $dataField;
            },
            fn (DataField $dataField) => // transform the string back to an array
$dataField->getRawData()
        ))->addViewTransformer(new CallbackTransformer(
            fn (DataField $dataField) => ['value' => $dataField->getRawData()],
            function ($raw) {
                $dataField = new DataField();
                $dataField->setRawData($raw['value']);

                return $dataField;
            }
        ));
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'hierarchical_view';
    }

    #[\Override]
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        return [];
    }

    #[\Override]
    public function generateResponse(View $view, Request $request): Response
    {
        if (empty($view->getOptions()['parent'])) {
            throw new NotFoundHttpException('Parent menu not found');
        }
        $parentId = \explode(':', (string) $view->getOptions()['parent']);
        if (2 != \count($parentId)) {
            throw new NotFoundHttpException('Parent menu not found: '.$view->getOptions()['parent']);
        }

        $contentType = $view->getContentType();

        try {
            $document = $this->searchService->getDocument($contentType, $parentId[1]);
            $parent = $document->getRaw();
        } catch (\Exception) {
            throw new NotFoundHttpException('Parent menu not found: '.$view->getOptions()['parent']);
        }

        if (empty($parent)) {
            throw new NotFoundHttpException('Parent menu not found: '.$view->getOptions()['parent']);
        }

        $data = [];

        $form = $this->formFactory->create(ReorganizeType::class, $data, [
            'view' => $view,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $structure = Json::decode((string) $data['structure']);

            $this->reorder($view->getOptions()['parent'], $view, $structure);

            $this->logger->notice('form.view.hierarchical.reorganized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $view->getContentType()->getName(),
                'view_name' => $view->getName(),
                'view_label' => $view->getLabel(),
            ]);

            return new RedirectResponse($this->router->generate('data.draft_in_progress', [
                'contentTypeId' => $view->getContentType()->getId(),
            ], UrlGeneratorInterface::RELATIVE_PATH));
        }

        $response = new Response();
        $response->setContent($this->twig->render("@$this->templateNamespace/view/custom/".$this->getBlockPrefix().'.html.twig', [
            'parent' => $parent,
            'view' => $view,
            'form' => $form->createView(),
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
        ]));

        return $response;
    }

    /**
     * @param array<mixed> $structure
     */
    public function reorder(string $itemKey, View $view, array $structure): void
    {
        $temp = \explode(':', $itemKey);
        $type = $temp[0];
        $ouuid = $temp[1];
        try {
            $revision = $this->dataService->initNewDraft($type, $ouuid);
            $data = $revision->getRawData();
            $data[$view->getOptions()['field']] = [];
            foreach ($structure as $item) {
                $data[$view->getOptions()['field']][] = $item['id'];
                if (\explode(':', (string) $item['id'])[0] == $view->getContentType()->getName()) {
                    $this->reorder($item['id'], $view, $item['children'] ?? []);
                }
            }
            $revision->setRawData($data);
            $this->dataService->finalizeDraft($revision);
        } catch (\Exception $e) {
            $this->logger->warning('form.view.hierarchical.error_with_document', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }
    }
}
