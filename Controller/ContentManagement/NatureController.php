<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Nature\ReorderType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NatureController extends AppController
{
    const MAX_ELEM = 400;

    /**
     * @return RedirectResponse|Response
     * @Route("/content-type/nature/reorder/{contentType}", name="nature.reorder"))
     */
    public function reorderAction(ContentType $contentType, Request $request, ContentTypeService $contentTypeService)
    {
        @\trigger_error(\sprintf('The "%s::reorderAction" controller is deprecated. Use a sort view instead.', NatureController::class), E_USER_DEPRECATED);

        if (null == $contentType->getOrderField()) {
            $this->getLogger()->warning('log.nature.order_field_not_defined', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            ]);

            return $this->redirectToRoute('data.draft_in_progress', [
                'contentTypeId' => $contentType->getId(),
            ]);
        }

        $orderField = $contentTypeService->getChildByPath($contentType->getFieldType(), $contentType->getOrderField(), true);

        if (!$orderField) {
            $this->getLogger()->warning('log.nature.order_field_is_missing', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                'order_field_name' => $contentType->getOrderField(),
            ]);

            return $this->redirectToRoute('data.draft_in_progress', [
                'contentTypeId' => $contentType->getId(),
            ]);
        }

        if (null != $orderField->getRestrictionOptions()['minimum_role'] && !$this->isGranted($orderField->getRestrictionOptions()['minimum_role'])) {
            $this->getLogger()->warning('log.nature.not_authorized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                'order_field_name' => $contentType->getOrderField(),
            ]);

            return $this->redirectToRoute('data.draft_in_progress', [
                'contentTypeId' => $contentType->getId(),
            ]);
        }

        $result = $this->getElasticsearch()->search([
            'index' => $contentType->getEnvironment()->getAlias(),
            'type' => $contentType->getName(),
            'size' => 400,
            'body' => [
                'sort' => $contentType->getOrderField(),
            ],
        ]);

        if ($result['hits']['total'] > $this::MAX_ELEM) {
            $this->getLogger()->error('log.nature.too_many_documents', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                'order_field_name' => $contentType->getOrderField(),
                'total' => $result['hits']['total'],
            ]);
        }

        $data = [];

        $form = $this->createForm(ReorderType::class, $data, [
            'result' => $result,
        ]);

        $form->handleRequest($request);

        /** @var DataService $dataService */
        $dataService = $this->getDataService();
        $counter = 1;

        if ($form->isSubmitted()) {
            foreach ($request->request->get('reorder')['items'] as $itemKey => $value) {
                try {
                    $revision = $dataService->initNewDraft($contentType->getName(), $itemKey);
                    $data = $revision->getRawData();
                    $data[$contentType->getOrderField()] = $counter++;
                    $revision->setRawData($data);
                    $dataService->finalizeDraft($revision);
                } catch (Exception $e) {
                    $this->getLogger()->warning('log.nature.issue_with_document', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                        EmsFields::LOG_OUUID_FIELD => $itemKey,
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                    ]);
                }
            }

            $this->getLogger()->notice('log.nature.reordered', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            ]);

            return $this->redirectToRoute('data.draft_in_progress', [
                'contentTypeId' => $contentType->getId(),
            ]);
        }

        return $this->render('@EMSCore/nature/reorder.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
            'result' => $result,
        ]);
    }
}
