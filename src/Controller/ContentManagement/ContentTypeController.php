<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Form\ContentTypeJsonUpdate;
use EMS\CoreBundle\Entity\Form\EditFieldType;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\SubfieldType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\ContentTypeStructureType;
use EMS\CoreBundle\Form\Form\ContentTypeType;
use EMS\CoreBundle\Form\Form\ContentTypeUpdateType;
use EMS\CoreBundle\Form\Form\EditFieldTypeType;
use EMS\CoreBundle\Form\Form\ReorderType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Mapping;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Button;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContentTypeController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly ContentTypeService $contentTypeService, private readonly Mapping $mappingService, private readonly FormRegistryInterface $formRegistry)
    {
    }

    public static function isValidName(string $name): bool
    {
        if (\in_array($name, [Mapping::HASH_FIELD, Mapping::SIGNATURE_FIELD, Mapping::FINALIZED_BY_FIELD, Mapping::FINALIZATION_DATETIME_FIELD])) {
            return false;
        }

        return \preg_match('/^[a-z][a-z0-9\-_]*$/i', $name) && \strlen($name) <= 100;
    }

    public function updateFromJsonAction(ContentType $contentType, Request $request): Response
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

            return $this->redirectToRoute('contenttype.edit', [
                'id' => $contentType->getId(),
            ]);
        }

        return $this->render('@EMSCore/contenttype/json_update.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }

    public function removeAction(int $id): RedirectResponse
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository(ContentType::class);
        $contentType = $repository->findById($id);

        if (null === $contentType) {
            throw new NotFoundHttpException('Content Type not found');
        }

        // TODO test if there something published for this content type
        $contentType->setActive(false)->setDeleted(true);
        $em->persist($contentType);
        $em->flush();

        $this->logger->warning('log.contenttype.deleted', [
            EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);

        return $this->redirectToRoute('contenttype.index');
    }

    public function activateAction(ContentType $contentType): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        if ($contentType->getDirty()) {
            $this->logger->error('log.contenttype.dirty', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);

            return $this->redirectToRoute('contenttype.index');
        }

        $contentType->setActive(true);
        $em->persist($contentType);
        $em->flush();

        return $this->redirectToRoute('contenttype.index');
    }

    public function disableAction(ContentType $contentType): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        $contentType->setActive(false);
        $em->persist($contentType);
        $em->flush();

        return $this->redirectToRoute('contenttype.index');
    }

    public function refreshMappingAction(ContentType $id): Response
    {
        $this->contentTypeService->updateMapping($id);
        $this->contentTypeService->persist($id);

        return $this->redirectToRoute('contenttype.index');
    }

    public function addAction(Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var EnvironmentRepository $environmetRepository */
        $environmetRepository = $em->getRepository(Environment::class);

        $environments = $environmetRepository->findBy([
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
            /** @var ContentTypeRepository $contentTypeRepository */
            $contentTypeRepository = $em->getRepository(ContentType::class);

            $contentTypes = $contentTypeRepository->findBy([
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
                $contentType->setOrderKey($contentTypeRepository->nextOrderKey());
                $em->persist($contentType);
            }
            $em->flush();

            $this->logger->notice('log.contenttype.created', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
            ]);

            return $this->redirectToRoute('contenttype.edit', [
                'id' => $contentType->getId(),
            ]);
        }

        return $this->render('@EMSCore/contenttype/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function indexAction(Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository(ContentType::class);

        $contentTypes = $contentTypeRepository->findAll();

        $builder = $this->createFormBuilder([])
            ->add('reorder', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary ',
                ],
                'icon' => 'fa fa-reorder',
            ]);

        $names = [];
        foreach ($contentTypes as $contentType) {
            $names[] = $contentType->getName();
        }

        $builder->add('contentTypeNames', CollectionType::class, [
            // each entry in the array will be an "email" field
            'entry_type' => HiddenType::class,
            // these options are passed to each "email" type
            'entry_options' => [],
            'data' => $names,
        ]);

        $form = $builder->getForm();

        if ($request->isMethod('POST')) {
            $form = $request->get('form');
            if (isset($form['contentTypeNames']) && \is_array($form['contentTypeNames'])) {
                $counter = 1;
                foreach ($form['contentTypeNames'] as $name) {
                    /** @var ContentType $contentType */
                    $contentType = $contentTypeRepository->findOneBy([
                        'deleted' => false,
                        'name' => $name,
                    ]);
                    $contentType->setOrderKey($counter);
                    $em->persist($contentType);
                    ++$counter;
                }

                $em->flush();

                $this->logger->notice('log.contenttype.reordered', [
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                ]);
            }

            return $this->redirectToRoute('contenttype.index');
        }

        return $this->render('@EMSCore/contenttype/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function unreferencedAction(Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var EnvironmentRepository $environmetRepository */
        $environmetRepository = $em->getRepository(Environment::class);
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository(ContentType::class);

        if ($request->isMethod('POST')) {
            if (null != $request->get('envId') && null != $request->get('name')) {
                $defaultEnvironment = $environmetRepository->findOneById($request->get('envId'));
                if ($defaultEnvironment instanceof Environment) {
                    $contentType = new ContentType();
                    $contentType->setName($request->get('name'));
                    $contentType->setPluralName($contentType->getName());
                    $contentType->setSingularName($contentType->getName());
                    $contentType->setEnvironment($defaultEnvironment);
                    $contentType->setActive(true);
                    $contentType->setDirty(false);
                    $contentType->setOrderKey($contentTypeRepository->countContentType());

                    $em->persist($contentType);
                    $em->flush();

                    $this->logger->warning('log.contenttype.referenced', [
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);

                    return $this->redirectToRoute('contenttype.edit', [
                        'id' => $contentType->getId(),
                    ]);
                }
            }
            $this->logger->warning('log.contenttype.unreferenced_not_found', [
            ]);

            return $this->redirectToRoute('contenttype.unreferenced');
        }

        return $this->render('@EMSCore/contenttype/unreferenced.html.twig', [
            'referencedContentTypes' => $this->contentTypeService->getUnreferencedContentTypes(),
        ]);
    }

    /**
     * Try to find (recursively) if there is a new field to add to the content type.
     *
     * @param array<mixed> $formArray
     *
     * @throws ElasticmsException
     */
    private function addNewField(array $formArray, FieldType $fieldType): bool|string
    {
        if (\array_key_exists('add', $formArray)) {
            if (isset($formArray['ems:internal:add:field:name'])
                && 0 != \strcmp((string) $formArray['ems:internal:add:field:name'], '')
                && isset($formArray['ems:internal:add:field:class'])
                && 0 != \strcmp((string) $formArray['ems:internal:add:field:class'], '')) {
                if (static::isValidName($formArray['ems:internal:add:field:name'])) {
                    $fieldTypeNameOrServiceName = $formArray['ems:internal:add:field:class'];
                    $fieldName = $formArray['ems:internal:add:field:name'];
                    /** @var DataFieldType $dataFieldType */
                    $dataFieldType = $this->getDataFieldType($fieldTypeNameOrServiceName);
                    $child = new FieldType();
                    $child->setName($fieldName);
                    $child->setType($fieldTypeNameOrServiceName);
                    $child->setParent($fieldType);
                    $child->setOptions($dataFieldType->getDefaultOptions($fieldName));
                    $fieldType->addChild($child);
                    $this->logger->notice('log.contenttype.field.added', [
                        'field_name' => $fieldName,
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                    ]);

                    return '_ems_'.$child->getName().'_modal_options';
                } else {
                    $this->logger->error('log.contenttype.field.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/ !'.Mapping::HASH_FIELD.' !'.Mapping::HASH_FIELD,
                    ]);
                }
            } else {
                $this->logger->error('log.contenttype.field.name_mandatory', [
                ]);
            }

            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $out = $this->addNewField($formArray['ems_'.$child->getName()], $child);
                    if (false !== $out) {
                        return '_ems_'.$child->getName().$out;
                    }
                }
            }
        }

        return false;
    }

    public function editFieldAction(ContentType $contentType, FieldType $field, Request $request): Response
    {
        $editFieldType = new EditFieldType($field);

        /** @var Form $form */
        $form = $this->createForm(EditFieldTypeType::class, $editFieldType);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $subFieldName = '';
            if ($form->get('fieldType')->has('ems:internal:add:subfield:name')) {
                $subFieldName = $form->get('fieldType')->get('ems:internal:add:subfield:name')->getData();
            }

            /** @var Button $clickable */
            $clickable = $form->getClickedButton();

            return $this->treatFieldSubmit($contentType, $field, $clickable->getName(), $subFieldName);
        }

        return $this->render('@EMSCore/contenttype/field.html.twig', [
            'form' => $form->createView(),
            'field' => $field,
            'contentType' => $contentType,
        ]);
    }

    /**
     * Try to find (recursively) if there is a new field to add to the content type.
     *
     * @param array<mixed> $formArray
     */
    private function addNewSubfield(array $formArray, FieldType $fieldType): bool|string
    {
        if (\array_key_exists('subfield', $formArray)) {
            if (isset($formArray['ems:internal:add:subfield:name'])
                && 0 !== \strcmp((string) $formArray['ems:internal:add:subfield:name'], '')) {
                if (static::isValidName($formArray['ems:internal:add:subfield:name'])) {
                    $child = new FieldType();
                    $child->setName($formArray['ems:internal:add:subfield:name']);
                    $child->setType(SubfieldType::class);
                    $child->setParent($fieldType);
                    $fieldType->addChild($child);
                    $this->logger->notice('log.contenttype.subfield.added', [
                        'subfield_name' => $formArray['ems:internal:add:subfield:name'],
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                    ]);

                    return '_ems_'.$child->getName().'_modal_options';
                } else {
                    $this->logger->error('log.contenttype.subfield.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/',
                    ]);
                }
            } else {
                $this->logger->error('log.contenttype.subfield.name_mandatory', [
                ]);
            }

            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $out = $this->addNewSubfield($formArray['ems_'.$child->getName()], $child);
                    if (false !== $out) {
                        return '_ems_'.$child->getName().$out;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Try to find (recursively) if there is a field to duplicate.
     *
     * @param array<mixed> $formArray
     */
    private function duplicateField(array $formArray, FieldType $fieldType): bool|string
    {
        if (\array_key_exists('duplicate', $formArray)) {
            if (isset($formArray['ems:internal:add:subfield:target_name'])
                && 0 !== \strcmp((string) $formArray['ems:internal:add:subfield:target_name'], '')) {
                if (static::isValidName($formArray['ems:internal:add:subfield:target_name'])) {
                    $new = clone $fieldType;
                    $new->setName($formArray['ems:internal:add:subfield:target_name']);
                    if ($parent = $new->getParent()) {
                        $parent->addChild($new);
                    }

                    $this->logger->notice('log.contenttype.field.added', [
                        'field_name' => $formArray['ems:internal:add:subfield:target_name'],
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                    ]);

                    return 'first_ems_'.$new->getName().'_modal_options';
                } else {
                    $this->logger->error('log.contenttype.field.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/ !'.Mapping::HASH_FIELD.' !'.Mapping::HASH_FIELD,
                    ]);
                }
            } else {
                $this->logger->error('log.contenttype.field.name_mandatory', [
                ]);
            }

            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $out = $this->duplicateField($formArray['ems_'.$child->getName()], $child);
                    if (false !== $out) {
                        if (\is_string($out) && 'first' == \substr($out, 0, 5)) {
                            return \substr($out, 5);
                        }

                        return '_ems_'.$child->getName().$out;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Try to find (recursively) if there is a field to remove from the content type.
     *
     * @param array<mixed> $formArray
     */
    private function removeField(array $formArray, FieldType $fieldType): bool
    {
        if (\array_key_exists('remove', $formArray)) {
            $fieldType->setDeleted(true);
            $this->logger->notice('log.contenttype.field.deleted', [
                'field_name' => $fieldType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
            ]);

            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted() && $this->removeField($formArray['ems_'.$child->getName()], $child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Try to find (recursively) if there is a container where subfields must be reordered in the content type.
     *
     * @param array<mixed> $formArray
     */
    private function reorderFields(array $formArray, FieldType $fieldType): bool
    {
        if (\array_key_exists('reorder', $formArray)) {
            /** @var string[] $keys */
            $keys = \array_keys($formArray);
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $order = \array_search('ems_'.$child->getName(), $keys, true);
                    if (false === $order || !\is_int($order)) {
                        continue;
                    }
                    $child->setOrderKey($order);
                }
            }

            $this->logger->notice('log.contenttype.field.reordered', [
                'field_name' => $fieldType->getName(),
            ]);

            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted() && $this->reorderFields($formArray['ems_'.$child->getName()], $child)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function reorderAction(ContentType $contentType, Request $request): Response
    {
        $data = [];
        $form = $this->createForm(ReorderType::class, $data, [
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $structure = \json_decode((string) $data['items'], true, 512, JSON_THROW_ON_ERROR);
            $this->contentTypeService->reorderFields($contentType, $structure);

            return $this->redirectToRoute('contenttype.edit', ['id' => $contentType->getId()]);
        }

        return $this->render('@EMSCore/contenttype/reorder.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }

    public function editAction(int $id, Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository(ContentType::class);
        $contentType = $repository->findById($id);

        if (null === $contentType) {
            $this->logger->error('log.contenttype.not_found', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $id,
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);

            return $this->redirectToRoute('contenttype.index');
        }

        $environment = $contentType->getEnvironment();
        if (null === $environment) {
            throw new \RuntimeException('Unexpected null environment');
        }

        $inputContentType = $request->request->all('content_type');
        try {
            $mapping = $this->mappingService->getMapping([$environment->getName()]);
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
                $em->persist($contentType);
                $em->flush();

                if ($contentType->getDirty()) {
                    $this->logger->warning('log.contenttype.dirty', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);
                }
                if (\array_key_exists('saveAndClose', $inputContentType)) {
                    return $this->redirectToRoute('contenttype.index');
                } elseif (\array_key_exists('saveAndEditStructure', $inputContentType)) {
                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                    ]);
                } elseif (\array_key_exists('saveAndReorder', $inputContentType)) {
                    return $this->redirectToRoute('ems_contenttype_reorder', [
                        'contentType' => $id,
                    ]);
                }

                return $this->redirectToRoute('contenttype.edit', [
                    'id' => $id,
                ]);
            }
        }

        if ($contentType->getDirty()) {
            $this->logger->warning('log.contenttype.dirty', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            ]);
        }

        return $this->render('@EMSCore/contenttype/edit.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentType,
            'mapping' => $mapping,
        ]);
    }

    public function editStructureAction(int $id, Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository(ContentType::class);
        $contentType = $repository->findById($id);

        if (null === $contentType) {
            $this->logger->error('log.contenttype.not_found', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $id,
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);

            return $this->redirectToRoute('contenttype.index');
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
                    return $this->redirectToRoute('contenttype.edit', [
                        'id' => $id,
                    ]);
                }
                if (\array_key_exists('saveAndReorder', $inputContentType)) {
                    return $this->redirectToRoute('ems_contenttype_reorder', [
                        'contentType' => $id,
                    ]);
                }

                return $this->redirectToRoute('contenttype.structure', [
                    'id' => $id,
                ]);
            } else {
                if ($out = $this->addNewField($inputContentType['fieldType'], $contentType->getFieldType())) {
                    $contentType->getFieldType()->updateOrderKeys();

                    $em->persist($contentType);
                    $em->flush();

                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                        'open' => $out,
                    ]);
                } elseif ($out = $this->addNewSubfield($inputContentType['fieldType'], $contentType->getFieldType())) {
                    $contentType->getFieldType()->updateOrderKeys();
                    $em->persist($contentType);
                    $em->flush();

                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                        'open' => $out,
                    ]);
                } elseif ($out = $this->duplicateField($inputContentType['fieldType'], $contentType->getFieldType())) {
                    $contentType->getFieldType()->updateOrderKeys();
                    $em->persist($contentType);
                    $em->flush();

                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                        'open' => $out,
                    ]);
                } elseif ($this->removeField($inputContentType['fieldType'], $contentType->getFieldType())) {
                    $contentType->getFieldType()->updateOrderKeys();
                    $em->persist($contentType);
                    $em->flush();

                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                    ]);
                } elseif ($this->reorderFields($inputContentType['fieldType'], $contentType->getFieldType())) {
                    // $contentType->getFieldType()->updateOrderKeys();
                    $em->persist($contentType);
                    $em->flush();

                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                    ]);
                }
            }
        }

        if ($contentType->getDirty()) {
            $this->logger->warning('log.contenttype.dirty', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            ]);
        }

        return $this->render('@EMSCore/contenttype/structure.html.twig', [
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
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
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
                return $this->redirectToRoute('ems_contenttype_reorder', [
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
                            $em->persist($field);
                            $em->flush();

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

        return $this->redirectToRoute('ems_contenttype_field_edit', [
            'contentType' => $contentType->getId(),
            'field' => $field->getId(),
        ]);
    }

    public function exportAction(ContentType $contentType): Response
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

    protected function getDataFieldType(string $fieldTypeNameOrServiceName): DataFieldType
    {
        $dataFieldType = $this->formRegistry->getType($fieldTypeNameOrServiceName)->getInnerType();
        if ($dataFieldType instanceof DataFieldType) {
            return $dataFieldType;
        }
        throw new ElasticmsException(\sprintf('Expecting a DataFieldType instance, got a %s', $dataFieldType::class));
    }
}
