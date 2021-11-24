<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Core\Revision\Json\JsonMenuRenderer;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\RevisionJsonMenuNestedType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Service\DataService;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class JsonMenuNestedController extends AbstractController
{
    private AjaxService $ajax;
    private JsonMenuRenderer $jsonMenuRenderer;
    private DataService $dataService;

    public function __construct(
        AjaxService $ajax,
        JsonMenuRenderer $jsonMenuRenderer,
        DataService $dataService
    ) {
        $this->ajax = $ajax;
        $this->jsonMenuRenderer = $jsonMenuRenderer;
        $this->dataService = $dataService;
    }

    public function modal(Request $request, Revision $revision, FieldType $fieldType): JsonResponse
    {
        $requestData = $this->getRequestJsonData($request);
        $level = \intval($request->get('level'));

        $newLevel = $level + 1;
        $maxDepth = $fieldType->getRestrictionOption('json_nested_max_depth', 0);
        if ($maxDepth > 0 && $newLevel > $maxDepth) {
            throw new \RuntimeException(\sprintf('Max depth is %d', $maxDepth));
        }

        $form = $this->getForm($revision, $fieldType, $requestData);
        $form->handleRequest($request);

        if (null !== $successResponse = $this->validateForm($form, $revision, $fieldType, $request, $level)) {
            return $successResponse;
        }

        return $this
            ->getAjaxModal()
            ->setBody('modalNested', ['form' => $form->createView(), 'fieldType' => $fieldType])
            ->setFooter('modalSaveFooter')
            ->getResponse();
    }

    public function modalPreview(Request $request, FieldType $parentFieldType): JsonResponse
    {
        $data = $this->getRequestJsonData($request);
        $fieldType = isset($data['type']) ? $parentFieldType->getChildByName($data['type']) : null;

        $rawData = $data['object'] ?? [];

        if ($fieldType) {
            $contentType = new ContentType();
            $contentType->setFieldType($fieldType);
            $revision = new Revision();
            $revision->setRawData($rawData);
            $revision->setContentType($contentType);
            $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $rawData]);
            $dataFields = $this->dataService->getDataFieldsStructure($form->get('data'));
        }

        return $this
            ->getAjaxModal()
            ->setBody('modalPreview', [
                'rawData' => $data,
                'dataFields' => $dataFields ?? false,
            ])
            ->setFooter('modalFooterClose')
            ->getResponse();
    }

    public function paste(Request $request, Revision $revision, FieldType $fieldType): JsonResponse
    {
        $requestData = $this->getRequestJsonData($request);
        $copied = $requestData['copied'] ?? false;

        if (!$copied) {
            throw new \RuntimeException('Missing copied data');
        }

        $structure = 'root' === $copied['type'] ? $copied['children'] ?? [] : [$copied];

        return new JsonResponse([
            'html' => $this->jsonMenuRenderer->generateNestedPaste([
                'revision' => $revision,
                'field_type' => $fieldType,
                'structure' => Json::encode($structure),
                'actions' => \explode('|', $request->get('actions', [])),
            ]),
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function getRequestJsonData(Request $request): array
    {
        if ('json' === $request->getContentType()) {
            $requestContent = $request->getContent();

            return \is_string($requestContent) && \strlen($requestContent) > 0 ? Json::decode($requestContent) : [];
        }

        return [];
    }

    private function getAjaxModal(): AjaxModal
    {
        return $this->ajax->newAjaxModel(JsonMenuRenderer::NESTED_TEMPLATE);
    }

    /**
     * @param array<mixed> $requestData
     *
     * @return FormInterface<FormInterface>
     */
    private function getForm(Revision $revision, FieldType $fieldType, array $requestData): FormInterface
    {
        $data = RawDataTransformer::transform($fieldType, $requestData);
        $data['label'] = $requestData['label'] ?? null;

        return $this->createForm(RevisionJsonMenuNestedType::class, ['data' => $data], [
            'field_type' => $fieldType,
            'content_type' => $revision->getContentType(),
        ]);
    }

    /**
     * @param FormInterface<FormInterface> $form
     */
    private function validateForm(FormInterface $form, Revision $revision, FieldType $fieldType, Request $request, int $level): ?JsonResponse
    {
        if (!$form->isSubmitted()) {
            return null;
        }

        $formDataField = $form->get('data');
        $objectArray = RawDataTransformer::reverseTransform($fieldType, $form->getData()['data']);
        $isValid = $this->dataService->isValid($formDataField, null, $objectArray);

        if (!$isValid || !$form->isValid()) {
            return null;
        }

        $this->dataService->getPostProcessing()->jsonMenuNested($formDataField, $revision->giveContentType(), $objectArray);
        $itemId = $request->get('itemId', Uuid::uuid4());

        return $this->getAjaxModal()->getSuccessResponse([
            'itemId' => $itemId,
            'html' => $this->jsonMenuRenderer->generateNestedItem([
                'field_type' => $fieldType->getJsonMenuNestedEditor(),
                'revision' => $revision,
                'level' => $level,
                'type' => $fieldType->getName(),
                'object' => $objectArray,
                'id' => $itemId,
                'actions' => \explode('|', $request->get('actions', [])),
            ]),
        ]);
    }
}
