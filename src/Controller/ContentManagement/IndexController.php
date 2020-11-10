<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Elasticsearch\Client;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\IndexService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AppController
{
    /** @var IndexService */
    private $indexService;

    public function __construct(
        IndexService $indexService,
        LoggerInterface $logger,
        FormRegistryInterface $formRegistry,
        RequestRuntime $requestRuntime
    ) {
        parent::__construct($logger, $formRegistry, $requestRuntime);
        $this->indexService = $indexService;
    }

    /**
     * @return RedirectResponse
     *
     * @Route("/indexes/delete-orphans", name="ems_delete_ophean_indexes", methods={"POST"})
     */
    public function deleteOrphansIndexesAction()
    {
        $this->indexService->deleteOrphanIndexes();

        return $this->redirectToRoute('ems_environment_index');
    }
}
