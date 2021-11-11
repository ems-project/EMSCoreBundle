<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectRepository;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\NotFoundException;
use EMS\CoreBundle\Form\Form\RevisionJsonMenuNestedType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class JsonMenuNestedController extends AbstractController
{
    private RevisionService $revisionService;
    /** @var ObjectRepository|FieldTypeRepository */
    private $fieldTypeRepository;
    private DataService $dataService;
    private Environment $templating;

    public function __construct(
        RevisionService $revisionService,
        Registry $doctrine,
        DataService $dataService,
        Environment $templating
    ) {
        $this->revisionService = $revisionService;
        $this->dataService = $dataService;
        $this->fieldTypeRepository = $doctrine->getRepository(FieldType::class);
        $this->templating = $templating;
    }

    /**
     * @Route("/data/revisions-modal/{fieldType}", name="revision.edit.nested-preview-modal", methods={"POST"})
     */
    public function modalPreview(Request $request, FieldType $fieldType): JsonResponse
    {
        $rawData = [];

        if ('json' === $request->getContentType()) {
            $requestContent = $request->getContent();
            $rawData = \is_string($requestContent) ? \json_decode($requestContent, true) : [];
        }

        $subField = null;
        foreach ($fieldType->getChildren() as $child) {
            if (!$child instanceof FieldType || $child->getDeleted()) {
                continue;
            }
            if ($child->getName() === $rawData['type'] ?? null) {
                $subField = $child;
                break;
            }
        }

        if (!$subField instanceof FieldType) {
            return new JsonResponse(\array_filter([
                'html' => $this->renderView('@EMSCore/data/json-menu-nested-json-preview.html.twig', [
                    'rawData' => $rawData['object'] ?? [],
                ]),
            ]));
        }

        $rawObject = $rawData['object'] ?? [];

        $contentType = new ContentType();
        $contentType->setFieldType($subField);
        $revision = new Revision();
        $revision->setRawData($rawObject);
        $revision->setContentType($contentType);
        $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $rawObject]);
        $dataFields = $this->dataService->getDataFieldsStructure($form->get('data'));

        return new JsonResponse(\array_filter([
            'html' => $this->renderView('@EMSCore/data/json-menu-nested-preview.html.twig', [
                'dataFields' => $dataFields,
                'rawData' => $rawObject,
            ]),
        ]));
    }

    /**
     * @Route("/data/draft/edit/{revisionId}/nested-modal/{fieldTypeId}/{parentLevel}", name="revision.edit.nested-modal", methods={"POST"})
     */
    public function modal(Request $request, int $revisionId, int $fieldTypeId, int $parentLevel): JsonResponse
    {
        if (null === $revision = $this->revisionService->find($revisionId)) {
            throw new NotFoundHttpException('Unknown revision');
        }

        $fieldType = $this->fieldTypeRepository->find($fieldTypeId);

        if (null === $fieldType || !$fieldType instanceof FieldType) {
            throw new NotFoundException('Unknown fieldtype');
        }

        if (null === $jsonMenuNestedEditor = $fieldType->getJsonMenuNestedEditor()) {
            throw new NotFoundException('Json menu editor field type');
        }

        $level = $parentLevel + 1;
        $maxDepth = $jsonMenuNestedEditor->getRestrictionOption('json_nested_max_depth', 0);
        if ($maxDepth > 0 && $level > $maxDepth) {
            throw new \RuntimeException(\sprintf('Max depth is %d', $maxDepth));
        }

        $label = null;
        $rawData = [];

        if ('json' === $request->getContentType()) {
            $requestContent = $request->getContent();
            $rawData = \is_string($requestContent) ? \json_decode($requestContent, true) : [];
            $label = $rawData['label'] ?? null;
        }

        $data = RawDataTransformer::transform($fieldType, $rawData);
        $data['label'] = $label;

        $form = $this->createForm(RevisionJsonMenuNestedType::class, ['data' => $data], [
            'field_type' => $fieldType,
            'content_type' => $revision->getContentType(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $formDataField = $form->get('data');
            $objectArray = RawDataTransformer::reverseTransform($fieldType, $form->getData()['data']);
            $isValid = $this->dataService->isValid($formDataField, null, $objectArray);

            if ($isValid && $form->isValid()) {
                $this->dataService->getPostProcessing()->jsonMenuNested($formDataField, $revision->giveContentType(), $objectArray);

                return new JsonResponse([
                    'object' => $objectArray,
                    'label' => $objectArray['label'] ?? ' ',
                    'buttons' => $this->renderButtons($revision, $fieldType, $level, $maxDepth),
                ]);
            }
        }

        return new JsonResponse(\array_filter([
            'html' => $this->renderView('@EMSCore/data/json-menu-nested.html.twig', [
                'form' => $form->createView(),
                'fieldType' => $fieldType,
            ]),
        ]));
    }

    /**
     * @Route("/data/revisions/{revision}/{fieldType}/json-menu-nested-paste", name="emsco.json_menu_nested.paste", methods={"POST"})
     */
    public function paste(Request $request, Revision $revision, FieldType $fieldType): Response
    {
        $requestContent = $request->getContent();
        $requestData = \is_string($requestContent) ? Json::decode($requestContent) : [];

        if (!isset($requestData['paste'])) {
            throw new \RuntimeException('Missing data');
        }

        $template = $this->templating->resolveTemplate('@EMSCore/form/fields/json_menu_nested_editor.html.twig');

        return new JsonResponse([
            'html' => $template->renderBlock('renderPaste', [
                'fieldType' => $fieldType,
                'revision' => $revision,
                'data' => $requestData['paste'],
            ]),
        ]);
    }

    private function renderButtons(Revision $revision, FieldType $fieldType, int $level, int $maxDepth): string
    {
        $editorNodes = $fieldType->getJsonMenuNestedEditorNodes();
        $editorTemplate = $this->templating->load('@EMSCore/form/fields/json_menu_nested_editor.html.twig');

        return $editorTemplate->renderBlock('itemButtons', [
            'revision' => $revision,
            'nodes' => $editorNodes,
            'currentNode' => $editorNodes[$fieldType->getName()],
            'level' => $level,
            'maxDepth' => $maxDepth,
        ]);
    }
}
