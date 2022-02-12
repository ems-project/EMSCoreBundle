<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Elasticsearch\Response\Response as CommonResponse;
use EMS\CommonBundle\Elasticsearch\Response\ResponseInterface;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Document\DataLinks;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Repository\QuerySearchRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class QuerySearchService implements EntityServiceInterface
{
    private ElasticaService $elasticaService;
    private QuerySearchRepository $querySearchRepository;
    private LoggerInterface $logger;
    private ContentTypeService $contentTypeService;

    public function __construct(ContentTypeService $contentTypeService, ElasticaService $elasticaService, QuerySearchRepository $querySearchRepository, LoggerInterface $logger)
    {
        $this->elasticaService = $elasticaService;
        $this->querySearchRepository = $querySearchRepository;
        $this->logger = $logger;
        $this->contentTypeService = $contentTypeService;
    }

    /**
     * @return QuerySearch[]
     */
    public function getAll(): array
    {
        return $this->querySearchRepository->getAll();
    }

    public function update(QuerySearch $querySearch): void
    {
        if (0 === $querySearch->getOrderKey()) {
            $querySearch->setOrderKey($this->querySearchRepository->counter() + 1);
        }
        $this->querySearchRepository->create($querySearch);
    }

    public function delete(QuerySearch $querySearch): void
    {
        $name = $querySearch->getName();
        $this->querySearchRepository->delete($querySearch);
        $this->logger->warning('log.service.query_search.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->querySearchRepository->getByIds($ids) as $querySearch) {
            $this->delete($querySearch);
        }
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $querySearch = $this->querySearchRepository->getById($id);
            $querySearch->setOrderKey($counter++);
            $this->querySearchRepository->create($querySearch);
        }
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function getOneByName(string $name): ?QuerySearch
    {
        /** @var QuerySearch|null $querySearch */
        $querySearch = $this->querySearchRepository->findOneBy(['name' => $name]);

        return $querySearch;
    }

    /**
     * @param mixed $context
     *
     * @return QuerySearch[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->querySearchRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'query_search';
    }

    /**
     * @param mixed $context
     */
    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->querySearchRepository->counter();
    }

    public function searchAndGetDatalinks(Request $request, string $querySearchName): JsonResponse
    {
        $dataLinks = new DataLinks($request);
        $commonSearchResponse = $this->commonSearch($querySearchName, $dataLinks);

        $dataLinks->addSearchResponse($commonSearchResponse);

        return new JsonResponse($dataLinks->toArray());
    }

    private function commonSearch(string $querySearchName, DataLinks $dataLinks): ResponseInterface
    {
        $querySearch = $this->getOneByName($querySearchName);
        if (!$querySearch instanceof QuerySearch) {
            throw new \RuntimeException(\sprintf('QuerySearch %s not found', $querySearchName));
        }

        $query = $querySearch->getOptions()['query'] ?? null;
        if (!\is_string($query)) {
            throw new \RuntimeException('Query search not defined');
        }

        $encodedPattern = Json::encode($dataLinks->getPattern());
        $encodedPattern = \substr($encodedPattern, 1, \strlen($encodedPattern) - 2);
        $query = \str_replace(['%query%'], [$encodedPattern], $query);

        $aliases = $this->getAliasesFromEnvironments($querySearch->getEnvironments());
        $query = Json::decode($query);

        $commonSearch = $this->elasticaService->convertElasticsearchBody($aliases, [], $query);
        $commonSearch->addTermsAggregation(AggregateOptionService::CONTENT_TYPES_AGGREGATION, EMSSource::FIELD_CONTENT_TYPE, 30);
        $commonSearch->setFrom($dataLinks->getFrom());
        $commonSearch->setSize($dataLinks->getSize());
        $resultSet = $this->elasticaService->search($commonSearch);

        foreach ($resultSet->getAggregation('types')['buckets'] ?? [] as $bucket) {
            if (!\is_string($bucket['key'] ?? null)) {
                continue;
            }

            $contentType = $this->contentTypeService->getByName($bucket['key']);
            if (!$contentType instanceof ContentType) {
                continue;
            }

            $dataLinks->addContentType($contentType);
        }

        return CommonResponse::fromResultSet($resultSet);
    }

    /**
     * @param Environment[] $environments
     *
     * @return string[]
     */
    private function getAliasesFromEnvironments(array $environments): array
    {
        $aliases = [];
        foreach ($environments as $environment) {
            $aliases[] = $environment->getAlias();
        }

        return $aliases;
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->getOneByName($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name = null): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}
