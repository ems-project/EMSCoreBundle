<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Component;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CommonBundle\Json\JsonMenuNestedException;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfigException;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedNode;
use EMS\CoreBundle\Core\Component\JsonMenuNested\JsonMenuNestedService;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\JsonMenuNestedTemplate;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Core\UI\AjaxModalResponse;
use EMS\CoreBundle\Core\UI\AjaxService;
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
        private readonly AjaxService $ajaxService,
        private readonly DataService $dataService,
        private readonly FormFactory $formFactory,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function render(Request $request, JsonMenuNestedConfig $config): JsonResponse
    {
        $data = Json::decode($request->getContent());

        return new JsonResponse($this->jsonMenuNestedService->render($config, $data));
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
            $this->jsonMenuNestedService->itemAdd($config, $itemId, $data);
            $this->clearFlashes($request);

            return $this->responseSuccess();
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

                return JsonResponse::fromJsonString(AjaxModalResponse::success([
                    'load' => $parent->isRoot() ? null : $itemId,
                    'item' => $item->getId(),
                    'type' => $item->getType(),
                    'label' => $item->getLabel(),
                    'object' => $item->getObject(),
                ]));
            }

            return new JsonResponse($this->ajaxService
                ->ajaxModalTemplate(JsonMenuNestedTemplate::TWIG_TEMPLATE)
                ->setBlockTitle('jmn_modal_title')
                ->setBlockBody('jmn_modal_form')
                ->setBlockFooter('jmn_modal_footer_form')
                ->render([
                    'action' => 'add',
                    'form' => $form->createView(),
                    'node' => $node,
                    'parent' => $parent,
                ])
            );
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
            $warnings = [];

            if ($form->isSubmitted()) {
                if ($form->get('_item_hash')->getData() !== $item->getObjectHash()) {
                    $warnings[] = $this->translator->trans('json_menu_nested.error.item_edit_outdated', [], EMSCoreBundle::TRANS_COMPONENT);
                } elseif (null !== $object = $this->handleFormItem($form, $config, $node)) {
                    $this->jsonMenuNestedService->itemUpdate($config, $item, $object);
                    $this->clearFlashes($request);

                    return JsonResponse::fromJsonString(AjaxModalResponse::success(['item' => $item->getId()]));
                }
            }

            return new JsonResponse($this->ajaxService
                ->ajaxModalTemplate(JsonMenuNestedTemplate::TWIG_TEMPLATE)
                ->setBlockTitle('jmn_modal_title')
                ->setBlockBody('jmn_modal_form')
                ->setBlockFooter('jmn_modal_footer_form')

                ->render([
                    'action' => 'edit',
                    'form' => $form->createView(),
                    'node' => $node,
                    'item' => $item,
                ], $warnings)
            );
        } catch (JsonMenuNestedException|JsonMenuNestedConfigException $e) {
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

            return new JsonResponse($this->ajaxService
                ->ajaxModalTemplate(JsonMenuNestedTemplate::TWIG_TEMPLATE)
                ->setBlockTitle('jmn_modal_title')
                ->setBlockBody('jmn_modal_preview')
                ->setBlockFooter('jmn_modal_footer_close')
                ->render([
                    'action' => 'view',
                    'node' => $node,
                    'item' => $item,
                    'rawData' => $rawData,
                    'dataFields' => $dataFields ?? null,
                ])
            );
        } catch (JsonMenuNestedException $e) {
            return $this->responseWarningModal($e->getMessage());
        }
    }

    public function itemDelete(Request $request, JsonMenuNestedConfig $config, string $itemId): JsonResponse
    {
        try {
            $this->jsonMenuNestedService->itemDelete($config, $itemId);
            $this->clearFlashes($request);

            return $this->responseSuccess();
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

    private function responseSuccess(): JsonResponse
    {
        return new JsonResponse(['success' => true]);
    }

    private function responseWarning(string $warning): JsonResponse
    {
        return new JsonResponse([
            'warning' => $this->translator->trans($warning, [], EMSCoreBundle::TRANS_COMPONENT),
        ]);
    }

    private function responseWarningModal(string $warning): JsonResponse
    {
        return JsonResponse::fromJsonString(AjaxModalResponse::warning(
            $this->translator->trans($warning, [], EMSCoreBundle::TRANS_COMPONENT)
        ));
    }
}
