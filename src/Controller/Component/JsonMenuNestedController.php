<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Component;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CommonBundle\Json\JsonMenuNestedException;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfigException;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedNode;
use EMS\CoreBundle\Core\Component\JsonMenuNested\JsonMenuNestedService;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Core\UI\Modal\Modal;
use EMS\CoreBundle\Core\UI\Modal\ModalMessageType;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\RevisionJsonMenuNestedType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Service\DataService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

class JsonMenuNestedController
{
    public function __construct(
        private readonly JsonMenuNestedService $jsonMenuNestedService,
        private readonly DataService $dataService,
        private readonly FormFactory $formFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function render(Request $request, JsonMenuNestedConfig $config): JsonResponse
    {
        $data = Json::decode($request->getContent());

        return new JsonResponse($this->jsonMenuNestedService->render(
            $config,
            $data['active_item_id'] ?? null,
            $data['load_children_id'] ?? null,
            ...$data['load_parent_ids'] ?? []
        ));
    }

    public function item(JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $item = $config->jsonMenuNested->giveItemById($itemId);

            return new JsonResponse(['item' => $item->toArrayStructure(true)]);
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarning($e->getMessage());
        }
    }

    public function itemAdd(Request $request, JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $data = Json::decode($request->getContent());
            $addedItem = $this->jsonMenuNestedService->itemAdd($config, $itemId, $data);
            $this->clearFlashes($request);

            return $this->responseSuccess(['item' => $addedItem?->getData()]);
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarning($e->getMessage());
        }
    }

    public function itemModalAdd(Request $request, JsonMenuNestedConfig $config, string $itemId, int $nodeId): JsonResponse
    {
        try {
            $parent = $config->jsonMenuNested->giveItemById($itemId);
            $node = $config->nodes->getById($nodeId);

            $form = $this->createFormItem($config, $node);
            $form->handleRequest($request);

            if ($form->isSubmitted() && null !== $object = $this->handleFormItem($form, $config, $node)) {
                $item = $this->jsonMenuNestedService->itemCreate($config, $parent, $node, $object);
                $this->clearFlashes($request);

                return $this->responseSuccess([
                    'load' => $parent->isRoot() ? null : $itemId,
                    'item' => $item->getData(),
                ]);
            }

            return new JsonResponse($this->jsonMenuNestedService->itemModal($config, $parent, 'jmn_modal', [
                'action' => 'add',
                'form' => $form->createView(),
                'node' => $node,
            ]));
        } catch (JsonMenuNestedException|JsonMenuNestedConfigException $e) {
            return $this->responseWarningModal($e->getMessage());
        }
    }

    public function itemModalEdit(Request $request, JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $item = $config->jsonMenuNested->giveItemById($itemId);
            $node = $config->nodes->get($item);

            $form = $this->createFormItem($config, $node, $item);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                if ($form->get('_item_hash')->getData() !== $item->getObjectHash()) {
                    return $this->responseWarningModal($this->translator->trans('json_menu_nested.error.item_edit_outdated', [], EMSCoreBundle::TRANS_COMPONENT));
                }
                if (null !== $object = $this->handleFormItem($form, $config, $node)) {
                    $this->jsonMenuNestedService->itemUpdate($config, $item, $object);
                    $this->clearFlashes($request);

                    return $this->responseSuccess(['item' => $item->getData()]);
                }
            }

            return new JsonResponse($this->jsonMenuNestedService->itemModal($config, $item, 'jmn_modal', [
                'action' => 'edit',
                'form' => $form->createView(),
                'node' => $node,
            ]));
        } catch (JsonMenuNestedException|JsonMenuNestedConfigException $e) {
            return $this->responseWarningModal($e->getMessage());
        }
    }

    public function itemModalCustom(JsonMenuNestedConfig $config, string $itemId, string $modalName): JsonResponse
    {
        try {
            $item = $config->jsonMenuNested->giveItemById($itemId);
            $node = $config->nodes->getByType($item->getType());

            return new JsonResponse($this->jsonMenuNestedService->itemModal($config, $item, $modalName, [
                'node' => $node,
            ]));
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarningModal($e->getMessage());
        }
    }

    public function itemModalView(JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $item = $config->jsonMenuNested->giveItemById($itemId);
            $rawData = $item->getObject();

            try {
                $node = $config->nodes->get($item);
            } catch (JsonMenuNestedConfigException) {
                $node = false;
            }

            if ($node) {
                $contentType = new ContentType();
                $contentType->setFieldType($node->getFieldType());
                $revision = new Revision();
                $revision->setRawData($rawData);
                $revision->setContentType($contentType);
                $form = $this->formFactory->create(RevisionType::class, $revision, ['raw_data' => $rawData]);
                $dataFields = $this->dataService->getDataFieldsStructure($form->get('data'));
            }

            return new JsonResponse($this->jsonMenuNestedService->itemModal($config, $item, 'jmn_modal', [
                'action' => 'view',
                'rawData' => $rawData,
                'dataFields' => $dataFields ?? null,
                'node' => $node,
            ]));
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarningModal($e->getMessage());
        }
    }

    public function itemDelete(Request $request, JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $deleteItem = $config->jsonMenuNested->giveItemById($itemId);
            $this->jsonMenuNestedService->itemDelete($config, $deleteItem);
            $this->clearFlashes($request);

            return $this->responseSuccess(['item' => $deleteItem->getData()]);
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarning($e->getMessage());
        }
    }

    public function itemMove(Request $request, JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $data = Json::decode($request->getContent());
            $this->jsonMenuNestedService->itemMove($config, $itemId, $data);
            $this->clearFlashes($request);

            return $this->responseSuccess();
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarning($e->getMessage());
        }
    }

    public function itemCopy(JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $copyItem = $config->jsonMenuNested->giveItemById($itemId);
            $this->jsonMenuNestedService->itemCopy($config, $copyItem);

            return $this->responseSuccess(['copyId' => $copyItem->getId()]);
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarning($e->getMessage());
        }
    }

    public function itemPaste(JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $item = $config->jsonMenuNested->giveItemById($itemId);
            $pasteItem = $this->jsonMenuNestedService->itemPaste($config, $item);

            return $this->responseSuccess(['pasteId' => $pasteItem->getId()]);
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarning($e->getMessage());
        }
    }

    private function clearFlashes(Request $request): void
    {
        /** @var Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->clear();
    }

    private function createFormItem(JsonMenuNestedConfig $config, JsonMenuNestedNode $node, ?JsonMenuNested $item = null): FormInterface
    {
        $object = $item ? $item->getObject() : [];
        $data = RawDataTransformer::transform($node->getFieldType(), $object);

        return $this->formFactory->create(RevisionJsonMenuNestedType::class, ['data' => $data], [
            'field_type' => $node->getFieldType(),
            'content_type' => $config->revision->giveContentType(),
            'item' => $item,
            'locale' => $config->locale,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleFormItem(FormInterface $form, JsonMenuNestedConfig $config, JsonMenuNestedNode $node): ?array
    {
        $formDataField = $form->get('data');

        $contentType = $config->revision->giveContentType();
        $object = RawDataTransformer::reverseTransform($node->getFieldType(), $form->getData()['data']);

        $this->dataService->getPostProcessing()->jsonMenuNested($formDataField, $contentType, $object);
        $isValid = $this->dataService->isValid($formDataField, null, $object);

        return $isValid || $form->isValid() ? $object : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function responseSuccess(array $data = []): JsonResponse
    {
        return new JsonResponse([...\array_filter($data), ...['success' => true]]);
    }

    private function responseWarning(string $warning): JsonResponse
    {
        return new JsonResponse([
            'warning' => $this->translator->trans($warning, [], EMSCoreBundle::TRANS_COMPONENT),
        ]);
    }

    private function responseWarningModal(string $warning): JsonResponse
    {
        return new JsonResponse(Modal::forMessage(
            ModalMessageType::Warning,
            $this->translator->trans($warning, [], EMSCoreBundle::TRANS_COMPONENT),
            'Warning'
        ));
    }
}
