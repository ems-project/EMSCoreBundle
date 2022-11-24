<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Service\IndexService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;

class IndexController extends AbstractController
{
    private IndexService $indexService;

    public function __construct(
        IndexService $indexService
    ) {
        $this->indexService = $indexService;
    }

    public function deleteOrphansIndexesAction(): RedirectResponse
    {
        $this->indexService->deleteOrphanIndexes();

        return $this->redirectToRoute('ems_environment_index');
    }
}
