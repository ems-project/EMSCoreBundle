<?php

namespace EMS\CoreBundle\Controller\Views;

use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\SearchService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class HierarchicalController extends AppController
{
    /**
     * @Route("/views/hierarchical/item/{view}/{key}", name="views.hierarchical.item")
     */
    public function itemAction(View $view, string $key, ContentTypeService $contentTypeService, SearchService $searchService): Response
    {
        $ouuid = \explode(':', $key);
        $contentType = $contentTypeService->getByName($ouuid[0]);
        if (false === $contentType) {
            throw new NotFoundHttpException(\sprintf('Content type %s not found', $ouuid[0]));
        }
        try {
            $document = $searchService->getDocument($contentType, $ouuid[1]);
        } catch (NotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('Document %s not found', $ouuid[1]));
        }

        return $this->render('@EMSCore/view/custom/hierarchical_add_item.html.twig', [
                'data' => $document->getSource(),
                'view' => $view,
                'contentType' => $contentType,
                'key' => $ouuid,
                'child' => $key,
        ]);
    }
}
