<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Search;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Controller\ElasticsearchController;
use EMS\CoreBundle\Core\ContentType\ViewTypes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\QuerySearchService;
use EMS\CoreBundle\Service\SearchService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class QuerySearchController extends AbstractController
{
    private LoggerInterface $logger;
    private SearchService $searchService;
    private ElasticaService $elasticaService;
    private ContentTypeService $contentTypeService;
    private AuthorizationCheckerInterface $authorizationChecker;
    private ViewTypes $viewTypes;
    private QuerySearchService $querySearchService;
    private ElasticsearchController $elasticsearchController;

    public function __construct(
        LoggerInterface $logger,
        SearchService $searchService,
        ElasticaService $elasticaService,
        ContentTypeService $contentTypeService,
        AuthorizationCheckerInterface $authorizationChecker,
        ViewTypes $viewTypes,
        QuerySearchService $querySearchService,
        ElasticsearchController $elasticsearchController
    ) {
        $this->logger = $logger;
        $this->searchService = $searchService;
        $this->elasticaService = $elasticaService;
        $this->contentTypeService = $contentTypeService;
        $this->authorizationChecker = $authorizationChecker;
        $this->viewTypes = $viewTypes;
        $this->querySearchService = $querySearchService;
        $this->elasticsearchController = $elasticsearchController;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $querySearchName = $request->query->get('querySearch', null);
        if (\is_string($querySearchName) && '' !== $querySearchName) {
            return $this->querySearchService->searchAndGetDatalinks($request, $querySearchName);
        }

        return $this->elasticsearchController->deprecatedSearchApiAction($request);
    }
}
