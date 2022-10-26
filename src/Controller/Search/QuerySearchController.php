<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Search;

use EMS\CoreBundle\Controller\ElasticsearchController;
use EMS\CoreBundle\Core\Document\DataLinksFactory;
use EMS\CoreBundle\Service\QuerySearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class QuerySearchController extends AbstractController
{
    private QuerySearchService $querySearchService;
    private ElasticsearchController $elasticsearchController;
    private DataLinksFactory $dataLinksFactory;

    public function __construct(
        QuerySearchService $querySearchService,
        ElasticsearchController $elasticsearchController,
        DataLinksFactory $dataLinksFactory
    ) {
        $this->querySearchService = $querySearchService;
        $this->elasticsearchController = $elasticsearchController;
        $this->dataLinksFactory = $dataLinksFactory;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $dataLinks = $this->dataLinksFactory->create($request);

        if ($dataLinks->hasCustomViewRendered()) {
            return new JsonResponse($dataLinks->toArray());
        }

        if ($dataLinks->isQuerySearch()) {
            $this->querySearchService->querySearchDataLinks($dataLinks);
        } elseif (!$dataLinks->hasItems()) {
            $this->elasticsearchController->deprecatedSearchApiAction($request, $dataLinks);
        }

        return new JsonResponse($dataLinks->toArray());
    }
}
