<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Elasticsearch\Response\Response as CommonResponse;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Document\DataLinks;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Repository\QuerySearchRepository;
use Psr\Log\LoggerInterface;

final class QuerySearchService implements EntityServiceInterface
{
    private ElasticaService $elasticaService;
    private QuerySearchRepository $querySearchRepository;
    private LoggerInterface $logger;
    private ContentTypeService $contentTypeService;
    private EnvironmentService $environmentService;

    public function __construct(ContentTypeService $contentTypeService, ElasticaService $elasticaService, QuerySearchRepository $querySearchRepository, LoggerInterface $logger, EnvironmentService $environmentService)
    {
        $this->elasticaService = $elasticaService;
        $this->querySearchRepository = $querySearchRepository;
        $this->logger = $logger;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
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
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [
            'query-search',
            'query-searches',
            'Query-Search',
            'Query-Searches',
            'QuerySearch',
            'QuerySearches',
        ];
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

    public function querySearchDataLinks(DataLinks $dataLinks): void
    {
        $querySearch = $this->getOneByName($dataLinks->getQuerySearchName());
        if (!$querySearch instanceof QuerySearch) {
            throw new \RuntimeException(\sprintf('QuerySearch %s not found', $dataLinks->getQuerySearchName()));
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

            $dataLinks->addContentTypes($contentType);
        }

        $dataLinks->addSearchResponse(CommonResponse::fromResultSet($resultSet));
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
        $querySearch = $this->buildQuerySearch($json, $entity);
        $this->querySearchRepository->create($querySearch);

        return $querySearch;
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $querySearch = $this->buildQuerySearch($json);
        if (null !== $name && $querySearch->getName() !== $name) {
            throw new \RuntimeException(\sprintf('QuerySearch name mismatched: %s vs %s', $querySearch->getName(), $name));
        }
        $this->querySearchRepository->create($querySearch);

        return $querySearch;
    }

    public function deleteByItemName(string $name): string
    {
        $querySearch = $this->getOneByName($name);
        if (null === $querySearch) {
            throw new \RuntimeException(\sprintf('QuerySearch %s not found', $name));
        }
        $id = $querySearch->getId();
        $this->querySearchRepository->delete($querySearch);

        return $id;
    }

    private function buildQuerySearch(string $json, ?EntityInterface $entity = null): QuerySearch
    {
        $querySearch = QuerySearch::fromJson($json, $entity);
        foreach ($querySearch->getEnvironments() as $environment) {
            $querySearch->removeEnvironment($environment);
        }
        foreach (JsonClass::getCollectionEntityNames($json, 'environments') as $name) {
            $querySearch->addEnvironment($this->environmentService->giveByName($name));
        }

        return $querySearch;
    }
}
