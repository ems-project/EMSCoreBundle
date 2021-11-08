<?php

namespace EMS\CoreBundle\Controller\Api\Search;

use EMS\CommonBundle\Common\Standard\Json;
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
        $json = Json::decode((string) $request->getContent());
        $search = $this->getSearch($json);
        $resultSet = $this->elasticaService->search($search);

        return new JsonResponse($resultSet->getResponse()->getData());
    }

    public function count(Request $request): Response
    {
        $json = Json::decode((string) $request->getContent());
        $search = $this->getSearch($json);
        $count = $this->elasticaService->count($search);

        return new JsonResponse([
            'count' => $count,
        ]);
    }

    public function initScroll(Request $request): Response
    {
        $json = Json::decode((string) $request->getContent());
        $search = $this->getSearch($json);
        $expireTime = $json['expire-time'] ?? '3m';
        $scroll = $this->elasticaService->scrollById($search, $expireTime);

        return new JsonResponse([
            'scroll-id' => $scroll->getResponse()->getScrollId(),
        ]);
    }

    public function nextScroll(Request $request): Response
    {
        $json = Json::decode((string) $request->getContent());
        $expireTime = $json['expire-time'] ?? '3m';
        $scrollId = $json['scroll-id'] ?? null;
        if (!\is_string($scrollId)) {
            throw new \RuntimeException('Unexpected: scroll-id must be a string');
        }
        $response = $this->elasticaService->nextScroll($scrollId, $expireTime);

        return new JsonResponse($response->getData());
    }

    public function version(): Response
    {
        return new JsonResponse([
            'version' => $this->elasticaService->getVersion(),
        ]);
    }

    public function refresh(Request $request): Response
    {
        $json = Json::decode((string) $request->getContent());
        $index = $json['index'] ?? null;
        $success = $this->elasticaService->refresh($index);

        return new JsonResponse([
            'success' => $success,
        ]);
    }

    /**
     * @param array<mixed> $json
     */
    private function getSearch(array $json): Search
    {
        $data = $json['search'] ?? null;
        if (!\is_string($data)) {
            throw new \RuntimeException('Unexpected: search must be a string');
        }
        $search = Search::deserialize($data);

        return $search;
    }
}
