<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Common\ArrayHelper\RecursiveMapper;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Json\JsonMenuNested;
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
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class JsonMenuNestedController extends AbstractController
{
    private AjaxService $ajax;
    private JsonMenuRenderer $jsonMenuRenderer;
    private RevisionService $revisionService;
    private DataService $dataService;
    private UserService $userService;

    public function __construct(
        AjaxService $ajax,
        JsonMenuRenderer $jsonMenuRenderer,
        RevisionService $revisionService,
        DataService $dataService,
        UserService $userService
    ) {
        $this->ajax = $ajax;
        $this->jsonMenuRenderer = $jsonMenuRenderer;
        $this->revisionService = $revisionService;
        $this->dataService = $dataService;
        $this->userService = $userService;
    }

    public function modal(Request $request, Revision $revision, FieldType $fieldType): JsonResponse
    {
        $requestData = $this->getRequestData($request);
        $level = \intval($requestData['level']);

        $newLevel = $level + 1;
        $maxDepth = $fieldType->getRestrictionOption('json_nested_max_depth', 0);
        if ($maxDepth > 0 && $newLevel > $maxDepth) {
            throw new \RuntimeException(\sprintf('Max depth is %d', $maxDepth));
        }

        $data = RawDataTransformer::transform($fieldType, $requestData['object'] ?? []);
        $data['label'] = $requestData['object']['label'] ?? null;

        $form = $this->createForm(RevisionJsonMenuNestedType::class, ['data' => $data], [
            'field_type' => $fieldType,
            'content_type' => $revision->getContentType(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $formDataField = $form->get('data');
            $objectArray = RawDataTransformer::reverseTransform($fieldType, $form->getData()['data']);
            $isValid = $this->dataService->isValid($formDataField, null, $objectArray);

            if ($isValid || $form->isValid()) {
                $this->dataService->getPostProcessing()->jsonMenuNested($formDataField, $revision->giveContentType(), $objectArray);

                return $this->getAjaxModal()->getSuccessResponse([
                    'html' => $this->jsonMenuRenderer->generateNestedItem($requestData['config'], [
                        'id' => 'item',
                        'field_type' => $fieldType->getJsonMenuNestedEditor(),
                        'revision' => $revision,
                        'item_id' => $requestData['item_id'],
                        'item_level' => $newLevel,
                        'item_type' => $fieldType->getName(),
                        'item_object' => $objectArray,
                    ]),
                ]);
            }
        }

        return $this
            ->getAjaxModal()
            ->setBody('modalNested', ['form' => $form->createView(), 'data' => $requestData])
            ->setFooter('modalSaveFooter')
            ->getResponse();
    }

    public function modalPreview(Request $request, FieldType $parentFieldType): JsonResponse
    {
        $data = $this->getRequestData($request);
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
        $requestData = $this->getRequestData($request);
        $copied = $requestData['copied'] ?? false;

        if (!$copied) {
            throw new \RuntimeException('Missing copied data');
        }

        $structure = 'root' === $copied['type'] ? $copied['children'] ?? [] : [$copied];

        return new JsonResponse([
            'html' => $this->jsonMenuRenderer->generateNestedPaste($requestData['config'], [
                'id' => 'paste',
                'revision' => $revision,
                'field_type' => $fieldType,
                'structure' => Json::encode($structure),
            ]),
        ]);
    }

    public function silentPublish(Request $request, Revision $revision, FieldType $fieldType, string $field): JsonResponse
    {
        $currentRevision = $this->revisionService->getCurrentRevisionByOuuidAndContentType(
            $revision->getOuuid(),
            $revision->giveContentType()->getName()
        );

        if ($currentRevision && $currentRevision->getId() !== $revision->getId()) {
            return new JsonResponse([
                'alert' => $this->jsonMenuRenderer->generateAlertOutOfSync(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requestData = $this->getRequestData($request);
        $updateJson = $requestData['update'];

        $rawData = $revision->getRawData();
        RecursiveMapper::mapPropertyValue($rawData, function (string $property, $v) use ($field, $updateJson) {
            if ($property !== $field) {
                return $v;
            }

            $currentJsonMenuNested = JsonMenuNested::fromStructure($v);
            $updateJsonMenuNested = new JsonMenuNested(Json::decode($updateJson));

            if ('root' === $updateJsonMenuNested->getId()) {
                return Json::encode($updateJsonMenuNested->toArrayStructure());
            }

            if ($item = $currentJsonMenuNested->getItemById($updateJsonMenuNested->getId())) {
                $item->setChildren($updateJsonMenuNested->getChildren());
            }

            return JSON::encode($currentJsonMenuNested->toArrayStructure());
        });

        $username = $this->userService->getCurrentUser()->getUsername();
        $updatedRevision = $this->revisionService->updateRawData($revision, $rawData, $username);

        return new JsonResponse($this->jsonMenuRenderer->generateSilentPublished($requestData['config'], [
            'id' => 'silent_publish',
            'silent_publish' => true,
            'revision' => $updatedRevision,
            'field_document' => $field,
            'field_type' => $fieldType,
        ]));
    }

    /**
     * @return array<mixed>
     */
    private function getRequestData(Request $request): array
    {
        if ('json' === $request->getContentType()) {
            $requestContent = $request->getContent();
            $decoded = \is_string($requestContent) && \strlen($requestContent) > 0 ? Json::decode($requestContent) : [];

            $data = $decoded['_data'] ?? [];
        } else {
            //modal form hidden field
            $data = $request->get('_data', null);
            $data = $data ? Json::decode($data) : [];
        }

        return $data;
    }

    private function getAjaxModal(): AjaxModal
    {
        return $this->ajax->newAjaxModel(JsonMenuRenderer::NESTED_TEMPLATE);
    }
}
