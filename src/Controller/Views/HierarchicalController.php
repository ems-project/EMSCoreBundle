<?php

namespace EMS\CoreBundle\Controller\Views;

use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HierarchicalController extends AppController
{
    /**
     * @param string $key
     *
     * @return Response
     *
     * @Route("/views/hierarchical/item/{view}/{key}", name="views.hierarchical.item")
     */
    public function itemAction(View $view, $key)
    {
        $ouuid = explode(':', $key);
        $contentType = $this->getContentTypeService()->getByName($ouuid[0]);
        $index = $this->getContentTypeService()->getIndex($contentType);
        $item = $this->getElasticsearch()->get([
                'index' => $index,
                'type' => $ouuid[0],
                'id' => $ouuid[1],
        ]);

        return $this->render('@EMSCore/view/custom/hierarchical_add_item.html.twig', [
                'data' => $item['_source'],
                'view' => $view,
                'contentType' => $contentType,
                'key' => $ouuid,
                'child' => $key,
        ]);
    }
}
