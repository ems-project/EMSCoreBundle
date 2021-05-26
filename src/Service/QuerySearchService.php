<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Elasticsearch\Response\Response as CommonResponse;
use EMS\CommonBundle\Elasticsearch\Response\ResponseInterface;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Document\DataLinks;
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
     * @param array<string, int> $ids
     */
    public function reorderByIds(array $ids): void
    {
        foreach ($this->querySearchRepository->getByIds(\array_keys($ids)) as $querySearch) {
            $querySearch->setOrderKey(isset($ids[$querySearch->getId()]) ? $ids[$querySearch->getId()] + 1 : 0);
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

    public function searchAndGetDatalinks(Request $request): JsonResponse
    {
        $commonSearchResponse = $this->commonSearch($request);

        $dataLinks = new DataLinks($request);
        $dataLinks->addSearchResponse($commonSearchResponse);

        return new JsonResponse($dataLinks->toArray());
    }

    private function commonSearch(Request $request): ResponseInterface
    {
        $querySearchName = $request->query->get('querySearch', null);
        $querySearch = $this->getOneByName($querySearchName);
        if (!$querySearch instanceof QuerySearch) {
            throw new \RuntimeException(\sprintf('QuerySearch %s not found', $querySearchName));
        }

        $aliases = $this->getAliasesFromEnvironments($querySearch->getEnvironments());
        $query = \json_decode($querySearch->getOptions()['query'], true);

        $commonSearch = $this->elasticaService->convertElasticsearchBody($aliases, [], $query);

        return CommonResponse::fromResultSet($this->elasticaService->search($commonSearch));
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
}
