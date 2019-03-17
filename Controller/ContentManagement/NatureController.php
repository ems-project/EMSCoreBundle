<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Nature\ReorderType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NatureController extends AppController
{

    const MAX_ELEM = 400;

    /**
     * @param ContentType $contentType
     * @param Request $request
     * @param ContentTypeService $contentTypeService
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @Route("/content-type/nature/reorder/{contentType}", name="nature.reorder"))
     */
    public function reorderAction(ContentType $contentType, Request $request, ContentTypeService $contentTypeService)
    {
        @trigger_error(sprintf('The "%s::reorderAction" controller is deprecated. Use a sort view instead.', NatureController::class), E_USER_DEPRECATED);

        if ($contentType->getOrderField() == null) {
            $this->addFlash('warning', 'This content type does not have any order field defined');

            return $this->redirectToRoute('data.draft_in_progress', [
                'contentTypeId' => $contentType->getId(),
            ]);
        }

        $orderField = $contentTypeService->getChildByPath($contentType->getFieldType(), $contentType->getOrderField(), true);

        if (!$orderField) {
            $this->addFlash('warning', 'It was not possible tio fing the order field defined');

            return $this->redirectToRoute('data.draft_in_progress', [
                'contentTypeId' => $contentType->getId(),
            ]);
        }


        if ($orderField->getRestrictionOptions()['minimum_role'] != null && !$this->isGranted($orderField->getRestrictionOptions()['minimum_role'])) {
            $this->addFlash('warning', 'Your user does not right to edit the order field');

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
            ]
        ]);

        if ($result['hits']['total'] > $this::MAX_ELEM) {
            $this->addFlash('warning', 'This content type have to much elements to reorder them all in once');
        }

        $data = [];

        $form = $this->createForm(ReorderType::class, $data, [
            'result' => $result,
        ]);


        $form->handleRequest($request);


        /** @var \EMS\CoreBundle\Service\DataService $dataService */
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
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'It was impossible to update the item ' . $itemKey . ': ' . $e->getMessage());
                }
            }

            $this->addFlash('notice', 'The ' . $contentType->getPluralName() . ' have been reordered');

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
