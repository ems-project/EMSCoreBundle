<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\IndexService;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AppController
{
    /** @var LoggerInterface */
    private $logger;
    /** @var IndexService */
    private $indexService;

    public function __construct(
        ClientInterface $elasticsearchClient,
        IndexService $indexService,
        LoggerInterface $logger,
        FormRegistryInterface $formRegistry,
        RequestRuntime $requestRuntime
    ) {
        $this->logger = $logger;
        parent::__construct($elasticsearchClient, $logger, $formRegistry, $requestRuntime);
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
