<?php

namespace EMS\CoreBundle\Controller\Api\Search;

use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController
{
    private ElasticaService $elasticaService;

    public function __construct(ElasticaService $elasticaService)
    {
        $this->elasticaService = $elasticaService;
    }

    public function search(Request $request): Response
    {
        $json = \json_decode((string) $request->getContent(), true);
        $data = $json['search'] ?? null;
        if (!\is_string($data)) {
            throw new \RuntimeException('Unexpected: search must be a string');
        }
        $search = Search::deserialize($data);
        $resultSet = $this->elasticaService->search($search);

        return new JsonResponse($resultSet->getResponse()->getData());
    }
}
