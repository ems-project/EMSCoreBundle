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

    public function healthStatus(): Response
    {
        return new JsonResponse([
            'status' => $this->elasticaService->getHealthStatus(),
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

    public function getAliasesFromIndex(Request $request): Response
    {
        $json = Json::decode((string) $request->getContent());
        $index = $json['index'] ?? null;
        if (!\is_string($index)) {
            throw new \RuntimeException('Unexpected: index must be a string');
        }

        return new JsonResponse([
            'aliases' => $this->elasticaService->getAliasesFromIndex($index),
        ]);
    }

    public function getIndicesFromAlias(Request $request): Response
    {
        $json = Json::decode((string) $request->getContent());
        $alias = $json['alias'] ?? null;
        if (!\is_string($alias)) {
            throw new \RuntimeException('Unexpected: index must be a string');
        }

        return new JsonResponse([
            'indices' => $this->elasticaService->getIndicesFromAlias($alias),
        ]);
    }

    public function getDocument(Request $request): Response
    {
        $json = Json::decode((string) $request->getContent());
        $index = $json['index'] ?? null;
        if (!\is_string($index)) {
            throw new \RuntimeException('Unexpected: index must be a string');
        }
        $contentType = $json['content-type'] ?? null;
        if (null !== $contentType && !\is_string($contentType)) {
            throw new \RuntimeException('Unexpected: content-type must be a string');
        }
        $ouuid = $json['ouuid'] ?? null;
        if (!\is_string($ouuid)) {
            throw new \RuntimeException('Unexpected: ouuid must be a string');
        }
        $sourceIncludes = $json['source-includes'] ?? [];
        if (!\is_array($sourceIncludes)) {
            throw new \RuntimeException('Unexpected: source-includes must be an array');
        }
        $sourcesExcludes = $json['source-excludes'] ?? [];
        if (!\is_array($sourcesExcludes)) {
            throw new \RuntimeException('Unexpected: source-excludes must be an array');
        }

        $document = $this->elasticaService->getDocument($index, $contentType, $ouuid, $sourceIncludes, $sourcesExcludes);

        return new JsonResponse([
            'source' => $document->getSource(),
            'content-type' => $document->getContentType(),
            'ouuid' => $document->getId(),
            'index' => $document->getIndex(),
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
