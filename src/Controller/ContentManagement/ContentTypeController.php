<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Form\FieldTypeManager;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeDataTableType;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeUnreferencedDataTableType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Form\ContentTypeJsonUpdate;
use EMS\CoreBundle\Entity\Form\EditFieldType;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\DataField\SubfieldType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Form\ContentTypeStructureType;
use EMS\CoreBundle\Form\Form\ContentTypeType;
use EMS\CoreBundle\Form\Form\ContentTypeUpdateType;
use EMS\CoreBundle\Form\Form\EditFieldTypeType;
use EMS\CoreBundle\Form\Form\ReorderType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Mapping;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Button;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Symfony\Component\Translation\t;

class ContentTypeController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly Mapping $mappingService,
        private readonly FieldTypeManager $fieldTypeManager,
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly FieldTypeRepository $fieldTypeRepository,
        private readonly string $templateNamespace
    ) {
    }

    /**
     * @deprecated
     */
    public static function isValidName(string $name): bool
    {
        @\trigger_error('Deprecated isValidName function, please use the FieldTypeManager::isValidName function', E_USER_DEPRECATED);

        return FieldTypeManager::isValidName($name);
    }

    public function updateFromJson(ContentType $contentType, Request $request): Response
    {
        $jsonUpdate = new ContentTypeJsonUpdate();
        $form = $this->createForm(ContentTypeUpdateType::class, $jsonUpdate);
        $form->handleRequest($request);

        $jsonUpdate = $form->getData();
        if ($form->isSubmitted() && $form->isValid()) {
            $json = \file_get_contents($jsonUpdate->getJson()->getRealPath());
            if (!\is_string($json)) {
                throw new NotFoundHttpException('JSON file not found');
            }

            $this->contentTypeService->updateFromJson($contentType, $json, $jsonUpdate->isDeleteExitingTemplates(), $jsonUpdate->isDeleteExitingViews());

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_EDIT, [
                'contentType' => $contentType->getId(),
            ]);
        }

        return $this->render("@$this->templateNamespace/contenttype/json_update.html.twig", [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }

    public function remove(ContentType $contentType): RedirectResponse
    {
        $this->contentTypeService->softDelete($contentType);

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_INDEX);
    }

    public function activate(ContentType $contentType): Response
    {
        if ($contentType->getDirty()) {
            $this->logger->error('log.contenttype.dirty', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_INDEX);
        }

        $contentType->setActive(true);
        $this->contentTypeRepository->save($contentType);

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_INDEX);
    }

    public function disable(ContentType $contentType): Response
    {
        $contentType->setActive(false);
        $this->contentTypeRepository->save($contentType);

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_INDEX);
    }

    public function refreshMapping(ContentType $contentType): Response
    {
        $this->contentTypeService->updateMapping($contentType);

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_INDEX);
    }

    public function add(Request $request): Response
    {
        $environments = $this->environmentRepository->findBy([
            'managed' => true,
        ]);

        $contentTypeAdded = new ContentType();
        $form = $this->createFormBuilder($contentTypeAdded)->add('name', IconTextType::class, [
            'icon' => 'fa fa-gear',
            'label' => 'Machine name',
            'required' => true,
        ])->add('singularName', TextType::class, [
        ])->add('pluralName', TextType::class, [
        ])->add('import', FileType::class, [
            'label' => 'Import From JSON',
            'mapped' => false,
            'required' => false,
        ])->add('environment', ChoiceType::class, [
            'label' => 'Default environment',
            'choices' => $environments,
            'choice_label' => fn (Environment $environment) => $environment->getName(),
        ])->add('save', SubmitType::class, [
            'label' => 'Create',
            'attr' => [
                'class' => 'btn btn-primary pull-right',
            ],
        ])->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ContentType $contentTypeAdded */
            $contentTypeAdded = $form->getData();
            $contentTypes = $this->contentTypeRepository->findBy([
                'name' => $contentTypeAdded->getName(),
                'deleted' => false,
            ]);

            if (0 != \count($contentTypes)) {
                $form->get('name')->addError(new FormError('Another content type named '.$contentTypeAdded->getName().' already exists'));
            }

            if (!static::isValidName($contentTypeAdded->getName())) {
                $form->get('name')->addError(new FormError('The content type name is malformed (format: [a-z][a-z0-9_-]*)'));
            }

            $normData = $form->get('import')->getNormData();
            if ($normData) {
                $name = $contentTypeAdded->getName();
                $pluralName = $contentTypeAdded->getPluralName();
                $singularName = $contentTypeAdded->getSingularName();
                $environment = $contentTypeAdded->getEnvironment();
                /** @var UploadedFile $file */
                $file = $request->files->get('form')['import'];
                $realPath = $file->getRealPath();
                $json = $realPath ? \file_get_contents($realPath) : false;

                if (!\is_string($json)) {
                    throw new NotFoundHttpException('JSON file not found');
                }
                if (!$environment instanceof Environment) {
                    throw new NotFoundHttpException('Environment not found');
                }
                $contentType = $this->contentTypeService->contentTypeFromJson($json, $environment);
                $contentType->setName($name);
                $contentType->setSingularName($singularName);
                $contentType->setPluralName($pluralName);
                $contentType = $this->contentTypeService->importContentType($contentType);
            } else {
                $contentType = $contentTypeAdded;
                $contentType->setAskForOuuid(false);
                $contentType->setOrderKey($this->contentTypeRepository->nextOrderKey());
                $this->contentTypeRepository->save($contentType);
            }

            $this->logger->notice('log.contenttype.created', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
            ]);

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_EDIT, [
                'contentType' => $contentType->getId(),
            ]);
        }

        return $this->render("@$this->templateNamespace/contenttype/add.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(ContentTypeDataTableType::class);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'content_type'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                ContentTypeDataTableType::ACTION_ACTIVATE => $this->contentTypeService->activateByIds(...$table->getSelected()),
                ContentTypeDataTableType::ACTION_DEACTIVATE => $this->contentTypeService->deactivateByIds(...$table->getSelected()),
                ContentTypeDataTableType::ACTION_UPDATE_MAPPING => $this->contentTypeService->updateMappingByIds(...$table->getSelected()),
                TableAbstract::DELETE_ACTION => $this->contentTypeService->softDeleteById(...$table->getSelected()),
                TableType::REORDER_ACTION => $this->contentTypeService->reorderByIds(
                    ...TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_INDEX);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-sitemap',
            'title' => t('type.title_overview', ['type' => 'content_type'], 'emsco-core'),
            'breadcrumb' => Navigation::admin()->contentTypes(),
        ]);
    }

    public function addReferencedIndex(): Response
    {
        $table = $this->dataTableFactory->create(ContentTypeUnreferencedDataTableType::class);
        $form = $this->createForm(TableType::class, $table);

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'title' => t('action.add_referenced_content_type', [], 'emsco-core'),
            'breadcrumb' => Navigation::admin()->contentTypes()->add(
                label: t('action.add_referenced', [], 'emsco-core'),
                icon: 'fa fa-plus',
                route: Routes::ADMIN_CONTENT_TYPE_ADD_REFERENCED_INDEX,
            ),
        ]);
    }

    public function addReferenced(Environment $environment, string $name): RedirectResponse
    {
        $contentType = new ContentType();
        $contentType->setName($name);
        $contentType->setPluralName($name);
        $contentType->setSingularName($name);
        $contentType->setEnvironment($environment);
        $contentType->setActive(true);
        $contentType->setDirty(false);
        $contentType->setOrderKey($this->contentTypeService->count());

        $this->contentTypeService->update($contentType);

        $this->logger->messageNotice(t(
            message: 'log.notice.content_type_referenced',
            parameters: ['contentType' => $contentType->getSingularName(), 'environment' => $environment->getLabel()],
            domain: 'emsco-core'
        ));

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_EDIT, [
            'contentType' => $contentType->getId(),
        ]);
    }

    public function editField(ContentType $contentType, FieldType $field, Request $request): Response
    {
        $editFieldType = new EditFieldType($field);

        $form = $this->createForm(EditFieldTypeType::class, $editFieldType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $subFieldName = '';
            if ($form->get('fieldType')->has('ems:internal:add:subfield:name')) {
                $subFieldName = $form->get('fieldType')->get('ems:internal:add:subfield:name')->getData();
            }

            $clickedButton = $form instanceof Form ? $form->getClickedButton() : null;
            $action = $clickedButton instanceof Button ? $clickedButton->getName() : 'unknown';

            return $this->treatFieldSubmit($contentType, $field, $action, $subFieldName);
        }

        return $this->render("@$this->templateNamespace/contenttype/field.html.twig", [
            'form' => $form->createView(),
            'field' => $field,
            'contentType' => $contentType,
        ]);
    }

    public function reorder(ContentType $contentType, Request $request): Response
    {
        $data = [];
        $form = $this->createForm(ReorderType::class, $data, [
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $structure = Json::decode((string) $data['items']);
            $this->contentTypeService->reorderFields($contentType, $structure);

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_EDIT, ['contentType' => $contentType->getId()]);
        }

        return $this->render("@$this->templateNamespace/contenttype/reorder.html.twig", [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }

    public function edit(ContentType $contentType, Request $request): Response
    {
        $environment = $contentType->giveEnvironment();

        $inputContentType = $request->request->all('content_type');
        try {
            $mapping = $this->mappingService->getMapping($environment);
        } catch (\Throwable) {
            $this->logger->warning('log.contenttype.mapping.not_found', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);
            $mapping = null;
        }

        $form = $this->createForm(ContentTypeType::class, $contentType, [
            'twigWithWysiwyg' => $contentType->getEditTwigWithWysiwyg(),
            'mapping' => $mapping,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contentType->getFieldType()->setName('source');

            if (\array_key_exists('save', $inputContentType) || \array_key_exists('saveAndUpdateMapping', $inputContentType) || \array_key_exists('saveAndClose', $inputContentType) || \array_key_exists('saveAndEditStructure', $inputContentType) || \array_key_exists('saveAndReorder', $inputContentType)) {
                if (\array_key_exists('saveAndUpdateMapping', $inputContentType)) {
                    $this->contentTypeService->updateMapping($contentType);
                }
                $this->contentTypeRepository->save($contentType);

                if ($contentType->getDirty()) {
                    $this->logger->warning('log.contenttype.dirty', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);
                }
                if (\array_key_exists('saveAndClose', $inputContentType)) {
                    return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_INDEX);
                } elseif (\array_key_exists('saveAndEditStructure', $inputContentType)) {
                    return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_STRUCTURE, [
                        'id' => $contentType->getId(),
                    ]);
                } elseif (\array_key_exists('saveAndReorder', $inputContentType)) {
                    return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_REORDER, [
                        'contentType' => $contentType->getId(),
                    ]);
                }

                return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_EDIT, [
                    'contentType' => $contentType->getId(),
                ]);
            }
        }

        if ($contentType->getDirty()) {
            $this->logger->warning('log.contenttype.dirty', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            ]);
        }

        return $this->render("@$this->templateNamespace/contenttype/edit.html.twig", [
            'form' => $form->createView(),
            'contentType' => $contentType,
            'mapping' => $mapping,
        ]);
    }

    public function editStructure(int $id, Request $request): Response
    {
        $contentType = $this->contentTypeRepository->findById($id);

        if (null === $contentType) {
            $this->logger->error('log.contenttype.not_found', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $id,
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_INDEX);
        }

        $inputContentType = $request->request->all('content_type_structure');

        $form = $this->createForm(ContentTypeStructureType::class, $contentType, [
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contentType->getFieldType()->setName('source');

            if (\array_key_exists('save', $inputContentType) || \array_key_exists('saveAndClose', $inputContentType) || \array_key_exists('saveAndReorder', $inputContentType)) {
                $contentType->getFieldType()->updateOrderKeys();
                $env = $contentType->getEnvironment();
                if (!$env) {
                    throw new \RuntimeException('Unexpected not found environment');
                }
                $managed = $env->getManaged();
                $contentType->setDirty($managed);

                if ((\array_key_exists('saveAndClose', $inputContentType) || \array_key_exists('saveAndReorder', $inputContentType)) && $contentType->getDirty()) {
                    $this->contentTypeService->updateMapping($contentType);
                }

                $this->contentTypeService->persist($contentType);

                if ($contentType->getDirty()) {
                    $this->logger->warning('log.contenttype.dirty', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);
                }
                if (\array_key_exists('saveAndClose', $inputContentType)) {
                    return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_EDIT, [
                        'contentType' => $id,
                    ]);
                }
                if (\array_key_exists('saveAndReorder', $inputContentType)) {
                    return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_REORDER, [
                        'contentType' => $id,
                    ]);
                }

                return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_STRUCTURE, [
                    'id' => $id,
                ]);
            } else {
                $openModal = $this->fieldTypeManager->handleRequest($contentType->getFieldType(), $inputContentType['fieldType']);
                $contentType->getFieldType()->updateOrderKeys();
                $this->contentTypeRepository->save($contentType);

                return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_STRUCTURE, \array_filter([
                    'id' => $id,
                    'open' => $openModal,
                ]));
            }
        }

        if ($contentType->getDirty()) {
            $this->logger->warning('log.contenttype.dirty', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            ]);
        }

        return $this->render("@$this->templateNamespace/contenttype/structure.html.twig", [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }

    /**
     * @param string $action
     * @param string $subFieldName
     */
    private function treatFieldSubmit(ContentType $contentType, FieldType $field, $action, $subFieldName): Response
    {
        $contentType->getFieldType()->setName('source');

        if (\in_array($action, ['save', 'saveAndClose'])) {
            $field->updateOrderKeys();
            $env = $contentType->getEnvironment();
            if (!$env) {
                throw new \RuntimeException('Unexpected not found environment');
            }
            $managed = $env->getManaged();
            $contentType->setDirty($managed);

            $this->contentTypeService->persist($contentType);
            $this->contentTypeService->persistField($field);

            if ($contentType->getDirty()) {
                $this->logger->warning('log.contenttype.dirty', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                ]);
            }

            if ('saveAndClose' === $action) {
                return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_REORDER, [
                    'contentType' => $contentType->getId(),
                ]);
            }
        } else {
            switch ($action) {
                case 'subfield':
                    if (static::isValidName($subFieldName)) {
                        try {
                            $child = new FieldType();
                            $child->setName($subFieldName);
                            $child->setType(SubfieldType::class);
                            $child->setParent($field);
                            $field->addChild($child);
                            $this->fieldTypeRepository->save($field);

                            $this->logger->notice('log.contenttype.subfield.added', [
                                'subfield_name' => $subFieldName,
                                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                            ]);
                        } catch (OptimisticLockException|ORMException $e) {
                            throw new ElasticmsException($e->getMessage());
                        }
                    } else {
                        $this->logger->error('log.contenttype.field.name_not_valid', [
                            'field_format' => '/[a-z][a-z0-9_-]*/ !'.Mapping::HASH_FIELD.' !'.Mapping::HASH_FIELD,
                        ]);
                    }
                    break;
                default:
                    $this->logger->warning('log.contenttype.action_not_found', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);
            }
        }

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_EDIT, [
            'contentType' => $contentType->getId(),
            'field' => $field->getId(),
        ]);
    }

    public function export(ContentType $contentType): Response
    {
        $jsonContent = Json::encode($contentType, true);

        $response = new Response($jsonContent);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $contentType->getName().'.json'
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
