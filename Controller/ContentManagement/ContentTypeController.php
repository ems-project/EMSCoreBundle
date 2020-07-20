<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Controller\AppController;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Button;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Operations on content types such as CRUD but alose rebuild index.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class ContentTypeController extends AppController
{


    public static function isValidName($name)
    {
        if (in_array($name, [Mapping::HASH_FIELD, Mapping::SIGNATURE_FIELD, Mapping::FINALIZED_BY_FIELD, Mapping::FINALIZATION_DATETIME_FIELD])) {
            return false;
        }
        return preg_match('/^[a-z][a-z0-9\-_]*$/i', $name) && strlen($name) <= 100;
    }

    /**
     * @Route("/content-type/json-update/{contentType}", name="emsco_contenttype_update_from_json"))
     */
    public function updateFromJsonAction(ContentType $contentType, Request $request, ContentTypeService $contentTypeService): Response
    {
        $jsonUpdate = new ContentTypeJsonUpdate();
        $form = $this->createForm(ContentTypeUpdateType::class, $jsonUpdate);
        $form->handleRequest($request);

        $jsonUpdate = $form->getData();
        if ($form->isSubmitted() && $form->isValid()) {
            $json = \file_get_contents($jsonUpdate->getJson()->getRealPath());
            if (! \is_string($json)) {
                throw new NotFoundHttpException('JSON file not found');
            }

            $contentTypeService->updateFromJson($contentType, $json, $jsonUpdate->isDeleteExitingTemplates(), $jsonUpdate->isDeleteExitingViews());
            return $this->redirectToRoute('contenttype.edit', [
                'id' => $contentType->getId()
            ]);
        }
        return $this->render('@EMSCore/contenttype/json_update.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }

    /**
     * Logically delete a content type.
     * GET calls aren't supported.
     *
     * @param int $id
     *            identifier of the content type to delete
     *
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/content-type/remove/{id}", name="contenttype.remove"), methods={"POST"})
     */
    public function removeAction($id, LoggerInterface $logger) : RedirectResponse
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');

        /** @var ContentType|null $contentType */
        $contentType = $repository->findById($id);

        if ($contentType === null) {
            throw new NotFoundHttpException('Content Type not found');
        }

        //TODO test if there something published for this content type
        $contentType->setActive(false)->setDeleted(true);
        $em->persist($contentType);
        $em->flush();

        $logger->warning('log.contenttype.deleted', [
            EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);

        return $this->redirectToRoute('contenttype.index');
    }

    /**
     * Activate (make it available for authors) a content type.
     * Checks that the content isn't dirty (as far as eMS knows the Mapping in Elasticsearch is up-to-date).
     *
     * @param ContentType $contentType
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/content-type/activate/{contentType}", name="contenttype.activate"), methods={"POST"})
     */
    public function activateAction(ContentType $contentType, LoggerInterface $logger)
    {

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        if ($contentType->getDirty()) {
            $logger->error('log.contenttype.dirty', [
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

    /**
     * Disable (make it unavailable for authors) a content type.
     *
     * @param ContentType $contentType
     * @return RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/content-type/disable/{contentType}", name="contenttype.desactivate"), methods={"POST"})
     */
    public function disableAction(ContentType $contentType)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        $contentType->setActive(false);
        $em->persist($contentType);
        $em->flush();
        return $this->redirectToRoute('contenttype.index');
    }

    /**
     * Try to update the Elasticsearch mapping for a specific content type
     *
     * @param ContentType $id
     * @return RedirectResponse
     * @throws BadRequestHttpException
     *
     * @Route("/content-type/refresh-mapping/{id}", name="contenttype.refreshmapping"), methods={"POST"})
     */
    public function refreshMappingAction(ContentType $id)
    {
        $this->getContentTypeService()->updateMapping($id);
        $this->getContentTypeService()->persist($id);
        return $this->redirectToRoute('contenttype.index');
    }

    /**
     * Initiate a new content type as a draft
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @return RedirectResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/content-type/add", name="contenttype.add")
     */
    public function addAction(Request $request, LoggerInterface $logger)
    {

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var EnvironmentRepository $environmetRepository */
        $environmetRepository = $em->getRepository('EMSCoreBundle:Environment');

        $environments = $environmetRepository->findBy([
            'managed' => true
        ]);

        $contentTypeAdded = new ContentType();
        $form = $this->createFormBuilder($contentTypeAdded)->add('name', IconTextType::class, [
            'icon' => 'fa fa-gear',
            'label' => "Machine name",
            'required' => true
        ])->add('singularName', TextType::class, [
        ])->add('pluralName', TextType::class, [
        ])->add('import', FileType::class, [
            'label' => 'Import From JSON',
            'mapped' => false,
            'required' => false
        ])->add('environment', ChoiceType::class, [
            'label' => 'Default environment',
            'choices' => $environments,
            'choice_label' => function (Environment $environment) {
                return $environment->getName();
            }
        ])->add('save', SubmitType::class, [
            'label' => 'Create',
            'attr' => [
                'class' => 'btn btn-primary pull-right'
            ]
        ])->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ContentType $contentTypeAdded */
            $contentTypeAdded = $form->getData();
            /** @var ContentTypeRepository $contentTypeRepository */
            $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

            $contentTypes = $contentTypeRepository->findBy([
                'name' => $contentTypeAdded->getName(),
                'deleted' => false
            ]);

            if (count($contentTypes) != 0) {
                $form->get('name')->addError(new FormError('Another content type named ' . $contentTypeAdded->getName() . ' already exists'));
            }

            if (!$this->isValidName($contentTypeAdded->getName())) {
                $form->get('name')->addError(new FormError('The content type name is malformed (format: [a-z][a-z0-9_-]*)'));
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $normData = $form->get("import")->getNormData();
                if ($normData) {
                    $name = $contentTypeAdded->getName();
                    $pluralName = $contentTypeAdded->getPluralName();
                    $singularName = $contentTypeAdded->getSingularName();
                    $environment = $contentTypeAdded->getEnvironment();
                    /** @var UploadedFile $file */
                    $file = $request->files->get('form')['import'];
                    $json = file_get_contents($file->getRealPath());

                    if (! \is_string($json)) {
                        throw new NotFoundHttpException('JSON file not found');
                    }
                    if (! $environment instanceof Environment) {
                        throw new NotFoundHttpException('Environment not found');
                    }
                    $contentType = $this->getContentTypeService()->contentTypeFromJson($json, $environment);
                    $contentType->setName($name);
                    $contentType->setSingularName($singularName);
                    $contentType->setPluralName($pluralName);
                    $contentType = $this->getContentTypeService()->importContentType($contentType);
                } else {
                    $contentType = $contentTypeAdded;
                    $contentType->setAskForOuuid(false);
                    $contentType->setViewRole('ROLE_AUTHOR');
                    $contentType->setEditRole('ROLE_AUTHOR');
                    $contentType->setCreateRole('ROLE_AUTHOR');
                    $contentType->setOrderKey($contentTypeRepository->nextOrderKey());
                    $em->persist($contentType);
                }
                $em->flush();

                $logger->notice('log.contenttype.created', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                ]);

                return $this->redirectToRoute('contenttype.edit', [
                    'id' => $contentType->getId()
                ]);
            } else {
                $logger->error('log.contenttype.created_failed', [
                ]);
            }
        }

        return $this->render('@EMSCore/contenttype/add.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * List all content types
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @return RedirectResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/content-type", name="contenttype.index"))
     */
    public function indexAction(Request $request, LoggerInterface $logger)
    {

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

        $contentTypes = $contentTypeRepository->findAll();

        $builder = $this->createFormBuilder([])
            ->add('reorder', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary '
                ],
                'icon' => 'fa fa-reorder'
            ]);

        $names = [];
        foreach ($contentTypes as $contentType) {
            $names[] = $contentType->getName();
        }

        $builder->add('contentTypeNames', CollectionType::class, array(
            // each entry in the array will be an "email" field
            'entry_type' => HiddenType::class,
            // these options are passed to each "email" type
            'entry_options' => array(),
            'data' => $names
        ));

        $form = $builder->getForm();

        if ($request->isMethod('POST')) {
            $form = $request->get('form');
            if (isset($form['contentTypeNames']) && is_array($form['contentTypeNames'])) {
                $counter = 0;
                foreach ($form['contentTypeNames'] as $name) {
                    $contentType = $contentTypeRepository->findOneBy([
                        'deleted' => false,
                        'name' => $name
                    ]);
                    if ($contentType) {
                        $contentType->setOrderKey($counter);
                        $em->persist($contentType);
                    }
                    ++$counter;
                }

                $em->flush();

                $logger->notice('log.contenttype.reordered', [
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                ]);
            }

            return $this->redirectToRoute('contenttype.index');
        }

        return $this->render('@EMSCore/contenttype/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * List all unreferenced content types (from external sources)
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @return RedirectResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/content-type/unreferenced", name="contenttype.unreferenced"))
     */
    public function unreferencedAction(Request $request, LoggerInterface $logger)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var EnvironmentRepository $environmetRepository */
        $environmetRepository = $em->getRepository('EMSCoreBundle:Environment');
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

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


                    $logger->warning('log.contenttype.referenced', [
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);

                    return $this->redirectToRoute('contenttype.edit', [
                        'id' => $contentType->getId()
                    ]);
                }
            }
            $logger->warning('log.contenttype.unreferenced_not_found', [
            ]);
            return $this->redirectToRoute('contenttype.unreferenced');
        }

        /** @var ContentTypeRepository $contenttypeRepository */
        $contenttypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

        $environments = $environmetRepository->findBy([
            'managed' => false
        ]);

        /** @var  Client $client */
        $client = $this->getElasticsearch();

        $referencedContentTypes = [];
        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $alias = $environment->getAlias();
            $mapping = $client->indices()->getMapping([
                'index' => $alias
            ]);
            foreach ($mapping as $indexName => $index) {
                foreach ($index ['mappings'] as $name => $type) {
                    $already = $contenttypeRepository->findByName($name);
                    if (!$already || $already->getDeleted()) {
                        $referencedContentTypes [] = [
                            'name' => $name,
                            'alias' => $alias,
                            'envId' => $environment->getId()
                        ];
                    }
                }
            }
        }

        return $this->render('@EMSCore/contenttype/unreferenced.html.twig', [
            'referencedContentTypes' => $referencedContentTypes
        ]);
    }

    /**
     * Try to find (recursively) if there is a new field to add to the content type
     *
     * @param array $formArray
     * @param FieldType $fieldType
     * @param LoggerInterface $logger
     * @return bool|string
     * @throws ElasticmsException
     */
    private function addNewField(array $formArray, FieldType $fieldType, LoggerInterface $logger)
    {
        if (array_key_exists('add', $formArray)) {
            if (isset($formArray ['ems:internal:add:field:name'])
                && strcmp($formArray ['ems:internal:add:field:name'], '') != 0
                && isset($formArray ['ems:internal:add:field:class'])
                && strcmp($formArray ['ems:internal:add:field:class'], '') != 0) {
                if ($this->isValidName($formArray ['ems:internal:add:field:name'])) {
                    $fieldTypeNameOrServiceName = $formArray ['ems:internal:add:field:class'];
                    $fieldName = $formArray ['ems:internal:add:field:name'];
                    /** @var DataFieldType $dataFieldType */
                    $dataFieldType = $this->getDataFieldType($fieldTypeNameOrServiceName);
                    $child = new FieldType();
                    $child->setName($fieldName);
                    $child->setType($fieldTypeNameOrServiceName);
                    $child->setParent($fieldType);
                    $child->setOptions($dataFieldType->getDefaultOptions($fieldName));
                    $fieldType->addChild($child);
                    $logger->notice('log.contenttype.field.added', [
                        'field_name' => $fieldName,
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE
                    ]);

                    return '_ems_' . $child->getName() . '_modal_options';
                } else {
                    $logger->error('log.contenttype.field.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/ !' . Mapping::HASH_FIELD . ' !' . Mapping::HASH_FIELD
                    ]);
                }
            } else {
                $logger->error('log.contenttype.field.name_mandatory', [
                ]);
            }
            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $out = $this->addNewField($formArray ['ems_' . $child->getName()], $child, $logger);
                    if ($out !== false) {
                        return '_ems_' . $child->getName() . $out;
                    }
                }
            }
        }
        return false;
    }


    /**
     * Edit a content type; generic information, but Nothing impacting its structure or it's mapping
     *
     * @param ContentType $contentType
     * @param FieldType $field
     * @param Request $request
     * @param LoggerInterface $logger
     * @return RedirectResponse|Response
     *
     * @throws ElasticmsException
     * @Route("/content-type/{contentType}/field/{field}", name="ems_contenttype_field_edit"))
     */
    public function editFieldAction(ContentType $contentType, FieldType $field, Request $request, LoggerInterface $logger)
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
            return $this->treatFieldSubmit($contentType, $field, $clickable->getName(), $subFieldName, $logger);
        }

        return $this->render('@EMSCore/contenttype/field.html.twig', [
            'form' => $form->createView(),
            'field' => $field,
            'contentType' => $contentType,
        ]);
    }

    /**
     * Try to find (recursively) if there is a new field to add to the content type
     *
     * @param array $formArray
     * @param FieldType $fieldType
     * @param LoggerInterface $logger
     * @return bool|string
     */
    private function addNewSubfield(array $formArray, FieldType $fieldType, LoggerInterface $logger)
    {
        if (array_key_exists('subfield', $formArray)) {
            if (isset($formArray ['ems:internal:add:subfield:name'])
                && strcmp($formArray ['ems:internal:add:subfield:name'], '') !== 0) {
                if ($this->isValidName($formArray ['ems:internal:add:subfield:name'])) {
                    $child = new FieldType();
                    $child->setName($formArray ['ems:internal:add:subfield:name']);
                    $child->setType(SubfieldType::class);
                    $child->setParent($fieldType);
                    $fieldType->addChild($child);
                    $logger->notice('log.contenttype.subfield.added', [
                        'subfield_name' => $formArray ['ems:internal:add:subfield:name'],
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE
                    ]);

                    return '_ems_' . $child->getName() . '_modal_options';
                } else {
                    $logger->error('log.contenttype.subfield.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/'
                    ]);
                }
            } else {
                $logger->error('log.contenttype.subfield.name_mandatory', [
                ]);
            }
            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $out = $this->addNewSubfield($formArray ['ems_' . $child->getName()], $child, $logger);
                    if ($out !== false) {
                        return '_ems_' . $child->getName() . $out;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Try to find (recursively) if there is a field to duplicate
     *
     * @param array $formArray
     * @param FieldType $fieldType
     * @param LoggerInterface $logger
     * @return bool|string
     */
    private function duplicateField(array $formArray, FieldType $fieldType, LoggerInterface $logger)
    {
        if (array_key_exists('duplicate', $formArray)) {
            if (isset($formArray ['ems:internal:add:subfield:target_name'])
                && strcmp($formArray ['ems:internal:add:subfield:target_name'], '') !== 0) {
                if ($this->isValidName($formArray ['ems:internal:add:subfield:target_name'])) {
                    $new = clone $fieldType;
                    $new->setName($formArray ['ems:internal:add:subfield:target_name']);
                    $new->getParent()->addChild($new);

                    $logger->notice('log.contenttype.field.added', [
                        'field_name' => $formArray ['ems:internal:add:subfield:target_name'],
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE
                    ]);

                    return 'first_ems_' . $new->getName() . '_modal_options';
                } else {
                    $logger->error('log.contenttype.field.name_not_valid', [
                        'field_format' => '/[a-z][a-z0-9_-]*/ !' . Mapping::HASH_FIELD . ' !' . Mapping::HASH_FIELD
                    ]);
                }
            } else {
                $logger->error('log.contenttype.field.name_mandatory', [
                ]);
            }
            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $out = $this->duplicateField($formArray ['ems_' . $child->getName()], $child, $logger);
                    if ($out !== false) {
                        if (substr($out, 0, 5) == 'first') {
                            return substr($out, 5);
                        }
                        return '_ems_' . $child->getName() . $out;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Try to find (recursively) if there is a field to remove from the content type
     *
     * @param array $formArray
     * @param FieldType $fieldType
     * @param LoggerInterface $logger
     * @return bool
     */
    private function removeField(array $formArray, FieldType $fieldType, LoggerInterface $logger)
    {
        if (array_key_exists('remove', $formArray)) {
            $fieldType->setDeleted(true);
            $logger->notice('log.contenttype.field.deleted', [
                'field_name' => $fieldType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE
            ]);
            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted() && $this->removeField($formArray['ems_' . $child->getName()], $child, $logger)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Try to find (recursively) if there is a container where subfields must be reordered in the content type
     *
     * @param array $formArray
     * @param FieldType $fieldType
     * @param LoggerInterface $logger
     * @return bool
     */
    private function reorderFields(array $formArray, FieldType $fieldType, LoggerInterface $logger)
    {
        if (array_key_exists('reorder', $formArray)) {
            $keys = array_keys($formArray);
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $child->setOrderKey(array_search('ems_' . $child->getName(), $keys));
                }
            }

            $logger->notice('log.contenttype.field.reordered', [
                'field_name' => $fieldType->getName(),
            ]);

            return true;
        } else {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted() && $this->reorderFields($formArray['ems_' . $child->getName()], $child, $logger)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Reorder a content type
     *
     * @param ContentType $contentType
     * @param Request $request
     * @return RedirectResponse|Response
     *
     * @Route("/content-type/reorder/{contentType}", name="ems_contenttype_reorder"))
     */
    public function reorderAction(ContentType $contentType, Request $request)
    {
        $data = [];
        $form = $this->createForm(ReorderType::class, $data, [
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $structure = json_decode($data['items'], true);
            $this->getContentTypeService()->reorderFields($contentType, $structure);
            return $this->redirectToRoute('contenttype.edit', ['id' => $contentType->getId()]);
        }

        return $this->render('@EMSCore/contenttype/reorder.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }

    /**
     * Edit a content type; generic information, but Nothing impacting its structure or it's mapping
     *
     * @param int $id
     * @param Request $request
     * @param LoggerInterface $logger
     * @return RedirectResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/content-type/{id}", name="contenttype.edit"))
     */
    public function editAction($id, Request $request, LoggerInterface $logger)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');

        /** @var ContentType|null $contentType */
        $contentType = $repository->findById($id);

        if ($contentType === null) {
            $logger->error('log.contenttype.not_found', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $id,
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);

            return $this->redirectToRoute('contenttype.index');
        }

        $inputContentType = $request->request->get('content_type');

        /** @var  Client $client */
        $client = $this->getElasticsearch();

        try {
            $mapping = $client->indices()->getMapping([
                'index' => $contentType->getEnvironment()->getAlias(),
                'type' => $contentType->getName()
            ]);
        } catch (\Throwable $e) {
            $logger->warning('log.contenttype.mapping.not_found', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);
            $mapping = [];
        }

        $form = $this->createForm(ContentTypeType::class, $contentType, [
            'twigWithWysiwyg' => $contentType->getEditTwigWithWysiwyg(),
            'mapping' => $mapping,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contentType->getFieldType()->setName('source');

            if (array_key_exists('save', $inputContentType) || array_key_exists('saveAndUpdateMapping', $inputContentType) || array_key_exists('saveAndClose', $inputContentType) || array_key_exists('saveAndEditStructure', $inputContentType) || array_key_exists('saveAndReorder', $inputContentType)) {
                if (array_key_exists('saveAndUpdateMapping', $inputContentType)) {
                    $this->getContentTypeService()->updateMapping($contentType);
                }
                $em->persist($contentType);
                $em->flush();

                if ($contentType->getDirty()) {
                    $logger->warning('log.contenttype.dirty', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);
                }
                if (array_key_exists('saveAndClose', $inputContentType)) {
                    return $this->redirectToRoute('contenttype.index');
                } else if (array_key_exists('saveAndEditStructure', $inputContentType)) {
                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id
                    ]);
                } else if (array_key_exists('saveAndReorder', $inputContentType)) {
                    return $this->redirectToRoute('ems_contenttype_reorder', [
                        'contentType' => $id
                    ]);
                }
                return $this->redirectToRoute('contenttype.edit', [
                    'id' => $id
                ]);
            }
        }


        if ($contentType->getDirty()) {
            $logger->warning('log.contenttype.dirty', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            ]);
        }

        return $this->render('@EMSCore/contenttype/edit.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentType,
            'mapping' => isset(current($mapping) ['mappings'] [$contentType->getName()] ['properties']) ? current($mapping) ['mappings'] [$contentType->getName()] ['properties'] : false
        ]);
    }

    /**
     * Edit a content type structure; add subfields.
     * Each times that a content type strucuture is saved the flag dirty is turned on.
     *
     * @param int $id
     * @param Request $request
     * @param LoggerInterface $logger
     * @return RedirectResponse|Response
     * @throws ElasticmsException
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/content-type/structure/{id}", name="contenttype.structure"))
     */
    public function editStructureAction($id, Request $request, LoggerInterface $logger)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');

        /** @var ContentType|null $contentType */
        $contentType = $repository->findById($id);

        if ($contentType === null) {
            $logger->error('log.contenttype.not_found', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $id,
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            ]);

            return $this->redirectToRoute('contenttype.index');
        }

        $inputContentType = $request->request->get('content_type_structure');

        $form = $this->createForm(ContentTypeStructureType::class, $contentType, [
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contentType->getFieldType()->setName('source');

            if (array_key_exists('save', $inputContentType) || array_key_exists('saveAndClose', $inputContentType) || array_key_exists('saveAndReorder', $inputContentType)) {
                $contentType->getFieldType()->updateOrderKeys();
                $contentType->setDirty($contentType->getEnvironment()->getManaged());

                if ((array_key_exists('saveAndClose', $inputContentType) || array_key_exists('saveAndReorder', $inputContentType)) && $contentType->getDirty()) {
                    $this->getContentTypeService()->updateMapping($contentType);
                }

                $this->getContentTypeService()->persist($contentType);

                if ($contentType->getDirty()) {
                    $logger->warning('log.contenttype.dirty', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);
                }
                if (array_key_exists('saveAndClose', $inputContentType)) {
                    return $this->redirectToRoute('contenttype.edit', [
                        'id' => $id
                    ]);
                }
                if (array_key_exists('saveAndReorder', $inputContentType)) {
                    return $this->redirectToRoute('ems_contenttype_reorder', [
                        'contentType' => $id
                    ]);
                }
                return $this->redirectToRoute('contenttype.structure', [
                    'id' => $id
                ]);
            } else {
                if ($out = $this->addNewField($inputContentType ['fieldType'], $contentType->getFieldType(), $logger)) {
                    $contentType->getFieldType()->updateOrderKeys();

                    $em->persist($contentType);
                    $em->flush();
                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                        'open' => $out,
                    ]);
                } else if ($out = $this->addNewSubfield($inputContentType ['fieldType'], $contentType->getFieldType(), $logger)) {
                    $contentType->getFieldType()->updateOrderKeys();
                    $em->persist($contentType);
                    $em->flush();
                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                        'open' => $out,
                    ]);
                } else if ($out = $this->duplicateField($inputContentType ['fieldType'], $contentType->getFieldType(), $logger)) {
                    $contentType->getFieldType()->updateOrderKeys();
                    $em->persist($contentType);
                    $em->flush();
                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id,
                        'open' => $out,
                    ]);
                } else if ($this->removeField($inputContentType ['fieldType'], $contentType->getFieldType(), $logger)) {
                    $contentType->getFieldType()->updateOrderKeys();
                    $em->persist($contentType);
                    $em->flush();
                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id
                    ]);
                } else if ($this->reorderFields($inputContentType ['fieldType'], $contentType->getFieldType(), $logger)) {
                    // $contentType->getFieldType()->updateOrderKeys();
                    $em->persist($contentType);
                    $em->flush();
                    return $this->redirectToRoute('contenttype.structure', [
                        'id' => $id
                    ]);
                }
            }
        }

        if ($contentType->getDirty()) {
            $logger->warning('log.contenttype.dirty', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            ]);
        }

        return $this->render('@EMSCore/contenttype/structure.html.twig', [
            'form' => $form->createView(),
            'contentType' => $contentType,
        ]);
    }


    /**
     * @param ContentType $contentType
     * @param FieldType $field
     * @param string $action
     * @param string $subFieldName
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @throws ElasticmsException
     */
    private function treatFieldSubmit(ContentType $contentType, FieldType $field, $action, $subFieldName, LoggerInterface $logger)
    {

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $contentType->getFieldType()->setName('source');

        if (in_array($action, ['save', 'saveAndClose'])) {
            $field->updateOrderKeys();
            $contentType->setDirty($contentType->getEnvironment()->getManaged());


            $this->getContentTypeService()->persist($contentType);
            $this->getContentTypeService()->persistField($field);

            if ($contentType->getDirty()) {
                $logger->warning('log.contenttype.dirty', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                ]);
            }

            if ($action === 'saveAndClose') {
                return $this->redirectToRoute('ems_contenttype_reorder', [
                    'contentType' => $contentType->getId()
                ]);
            }
        } else {
            switch ($action) {
                case 'subfield':
                    if ($this->isValidName($subFieldName)) {
                        try {
                            $child = new FieldType();
                            $child->setName($subFieldName);
                            $child->setType(SubfieldType::class);
                            $child->setParent($field);
                            $field->addChild($child);
                            $em->persist($field);
                            $em->flush();

                            $logger->notice('log.contenttype.subfield.added', [
                                'subfield_name' => $subFieldName,
                                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE
                            ]);
                        } catch (OptimisticLockException $e) {
                            throw new ElasticmsException($e->getMessage());
                        } catch (ORMException $e) {
                            throw new ElasticmsException($e->getMessage());
                        }
                    } else {
                        $logger->error('log.contenttype.field.name_not_valid', [
                            'field_format' => '/[a-z][a-z0-9_-]*/ !' . Mapping::HASH_FIELD . ' !' . Mapping::HASH_FIELD
                        ]);
                    }
                    break;
                default:
                    $logger->warning('log.contenttype.action_not_found', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);
            }
        }
        return $this->redirectToRoute('ems_contenttype_field_edit', [
            'contentType' => $contentType->getId(),
            'field' => $field->getId(),
        ]);
    }


    /**
     * Migrate a content type from its default index
     *
     * @param ContentType $contentType
     * @return RedirectResponse
     *
     * @Route("/content-type/migrate/{contentType}", name="contenttype.migrate"), methods={"POST"})
     */
    public function migrateAction(ContentType $contentType)
    {
        return $this->startJob('ems.contenttype.migrate', [
            'contentTypeName' => $contentType->getName()
        ]);
    }


    /**
     * Export a content type in Json format
     *
     * @param ContentType $contentType
     * @return Response
     *
     * @Route("/content-type/export/{contentType}.{_format}", defaults={"_format" = "json"}, name="contenttype.export"))
     */
    public function exportAction(ContentType $contentType)
    {
        $jsonContent = \json_encode($contentType);

        $response = new Response($jsonContent);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $contentType->getName() . '.json'
        );

        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }
}
