<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Search;

use EMS\CoreBundle\Core\Document\DataLinksFactory;
use EMS\CoreBundle\Service\QuerySearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class QuerySearchController extends AbstractController
{
    public function __construct(private readonly QuerySearchService $querySearchService, private readonly DataLinksFactory $dataLinksFactory)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $dataLinks = $this->dataLinksFactory->create($request);

        if ($dataLinks->hasCustomViewRendered()) {
            return new JsonResponse($dataLinks->toArray());
        }

        $querySearch = null;
        $contentTypes = $dataLinks->getContentTypes();
        if ($dataLinks->isQuerySearch()) {
            $querySearch = $this->querySearchService->getByItemName($dataLinks->getQuerySearchName());
        }
        if (null === $querySearch && 1 === \count($contentTypes) && $contentTypes[0]->getQuerySearch()) {
            $querySearch = $contentTypes[0]->getQuerySearch();
        } else {
            $querySearch = $this->querySearchService->getDefault();
        }
        if (null === $querySearch) {
            throw new NotFoundHttpException('Quick search dashboard not defined');
        }
        $this->querySearchService->querySearchDataLinks($dataLinks, $querySearch);

        return new JsonResponse($dataLinks->toArray());
    }
}
