<?php

namespace EMS\CoreBundle\Form\View;

use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use EMS\CoreBundle\Form\Nature\ReorganizeType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Twig_Environment;

/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class HierarchicalViewType extends ViewType
{

    /**@var Session $session*/
    protected $session;

    /**@var DataService */
    protected $dataService;

    /**@var Router */
    protected $router;

    /**@var ContentTypeService */
    protected $contentTypeService;

    public function __construct(FormFactory $formFactory, Twig_Environment $twig, Client $client, LoggerInterface $logger, Session $session, DataService $dataService, Router $router, ContentTypeService $contentTypeService)
    {
        parent::__construct($formFactory, $twig, $client, $logger);
        $this->session = $session;
        $this->dataService = $dataService;
        $this->router = $router;
        $this->contentTypeService = $contentTypeService;
    }

    public function getLabel()
    {
        return "Hierarchical: manage a menu structure (based on a ES query)";
    }
    
    public function getName()
    {
        return "Hierarchical";
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
        
        
        $fieldType = new FieldType();
        
        $builder
        ->add('parent', DataLinkFieldType::class, [
                'label' => 'Parent',
                'metadata' => $fieldType,
                'type' => $view->getContentType()->getName(),
                'multiple' => false,
                'dynamicLoading' => true
        ])
        ->add('size', IntegerType::class, [
                'label' => 'Limit the result to the x first results',
                'attr' => [
                ]
        ])
        ->add('maxDepth', IntegerType::class, [
                'label' => 'Limit the menu\'s depth',
                'attr' => [
                ]
        ])
        ->add('maxDepth', IntegerType::class, [
                'label' => 'Limit the menu\'s depth',
                'attr' => [
                ]
        ])
        ->add('field', ContentTypeFieldPickerType::class, [
                'label' => 'Target children field (datalink)',
                'required' => false,
                'firstLevelOnly' => false,
                'mapping' => $mapping,
                'types' => [
                        'keyword',
                        'text', //TODO: for ES2 support
                ]]);
        
        $builder->get('parent')->addModelTransformer(new CallbackTransformer(
            function ($raw) {
                    $dataField = new DataField();
                    $dataField->setRawData($raw);
                    return $dataField;
            },
            function (DataField $dataField) {
                    // transform the string back to an array
                    return $dataField->getRawData();
            }
        ))->addViewTransformer(new CallbackTransformer(
            function (DataField $dataField) {
                    return ['value' => $dataField->getRawData()];
            },
            function ($raw) {
                    $dataField = new DataField();
                    $dataField->setRawData($raw['value']);
                    return $dataField;
            }
        ));
    }
    
    public function getBlockPrefix()
    {
        return 'hierarchical_view';
    }
    

    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request)
    {
        
        return [];
    }
    
    public function generateResponse(View $view, Request $request)
    {
        
        if (empty($view->getOptions()['parent'])) {
            throw new NotFoundHttpException('Parent menu not found');
        }
        $parentId = explode(':', $view->getOptions()['parent']);
        if (count($parentId) != 2) {
            throw new NotFoundHttpException('Parent menu not found: ' . $view->getOptions()['parent']);
        }

        $index = $this->contentTypeService->getIndex($view->getContentType());

        $parent = null;
        try {
            $parent = $this->client->get([
                    'index' => $index,
                    'type' => $parentId[0],
                    'id' => $parentId[1],
            ]);
        } catch (Exception $e) {
            throw new NotFoundHttpException('Parent menu not found: ' . $view->getOptions()['parent']);
        }
        
        if (empty($parent)) {
            throw new NotFoundHttpException('Parent menu not found: ' . $view->getOptions()['parent']);
        }

        
        $data = [];

        $form = $this->formFactory->create(ReorganizeType::class, $data, [
                'view' => $view,
        ]);
        
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            $data = $form->getData();
            $structure = json_decode($data['structure'], true);

            $this->reorder($view->getOptions()['parent'], $view, $structure);

            $this->logger->notice('form.view.hierarchical.reorganized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $view->getContentType()->getName(),
                'view_name' => $view->getName(),
            ]);

            return new RedirectResponse($this->router->generate('data.customindexview', [
                    'viewId' => $view->getId(),
                    'reordered' => true
            ]));
        }

        $response = new Response();
        $response->setContent($this->twig->render('@EMSCore/view/custom/' . $this->getBlockPrefix() . '.html.twig', [
                'parent' => $parent,
                'view' => $view,
                'form' => $form->createView(),
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
                'reordered' => $request->get('reordered')
        ]));
        return $response;
    }
    
    public function reorder($itemKey, View $view, $structure)
    {
        $temp = explode(':', $itemKey);
        $type = $temp[0];
        $ouuid = $temp[1];
        try {
            $revision = $this->dataService->initNewDraft($type, $ouuid);
            $data = $revision->getRawData();
            $data[$view->getOptions()['field']] = [];
            foreach ($structure as $item) {
                $data[$view->getOptions()['field']][] = $item['id'];
                if (explode(':', $item['id'])[0] == $view->getContentType()->getName()) {
                    $this->reorder($item['id'], $view, isset($item['children']) ? $item['children'] : []);
                }
            }
            $revision->setRawData($data);
            $this->dataService->finalizeDraft($revision);
        } catch (Exception $e) {
            $this->logger->warning('form.view.hierarchical.error_with_document', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }
    }
}
