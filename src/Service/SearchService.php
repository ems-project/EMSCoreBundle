<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\Terms;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\Document as ElasticsearchDocument;
use EMS\CommonBundle\Search\Search as CommonSearch;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Repository\SearchRepository;

class SearchService
{
    public function __construct(
        private readonly Registry $doctrine,
        private readonly Mapping $mapping,
        private readonly ElasticaService $elasticaService,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly SearchRepository $searchRepository
    ) {
    }

    /**
     * @return Search[]
     */
    public function getAll(): array
    {
        return $this->searchRepository->getAll();
    }

    /**
     * @deprecated
     *
     * @return array<mixed>
     */
    public function generateSearchBody(Search $search): array
    {
        @\trigger_error('SearchService::generateSearchBody is deprecated use the SearchService::generateSearch method instead', E_USER_DEPRECATED);
        $commonSearch = $this->generateSearch($search);
        $body = [];
        $query = $commonSearch->getQuery();
        if (null !== $query) {
            $body['query'] = $query instanceof AbstractQuery ? $query->toArray() : $query;
        }
        $body['sort'] = $commonSearch->getSort();

        return $body;
    }

    public function generateSearch(Search $search): CommonSearch
    {
        $environments = \array_filter(
            \array_map(fn (string $name) => $this->environmentService->giveByName($name), $search->getEnvironments()),
            fn (Environment $e) => $this->elasticaService->hasIndex($e->getAlias())
        );

        $mapping = $this->mapping->getMapping(...$environments);

        $boolQuery = $this->elasticaService->getBoolQuery();

        foreach ($search->getFilters() as $filter) {
            if (null === $esFilter = $filter->generateEsFilter()) {
                continue;
            }

            if ($filter->getField() && ($nestedPath = $this->getNestedPath($filter->getField(), $mapping))) {
                $esFilter = $this->nestFilter($nestedPath, $esFilter);
            }

            switch ($filter->getBooleanClause()) {
                case 'must':
                    $boolQuery->addMust($esFilter);
                    break;
                case 'should':
                    $boolQuery->addShould($esFilter);
                    $boolQuery->setMinimumShouldMatch($search->getMinimumShouldMatch());
                    break;
                case 'must_not':
                    $boolQuery->addMustNot($esFilter);
                    break;
                case 'filter':
                    $boolQuery->addFilter((new BoolQuery())->addMust($esFilter));
                    break;
                default:
                    throw new \RuntimeException(\sprintf('Unexpected %s boolean clause', $filter->getBooleanClause()));
            }
        }
        if (0 === $boolQuery->count()) {
            $boolQuery = null;
        }

        $indexes = \array_map(static fn (Environment $e) => $e->getAlias(), $environments);
        $commonSearch = new CommonSearch($indexes, $this->elasticaService->filterByContentTypes($boolQuery, $search->getContentTypes()));

        $sortBy = $search->getSortBy();
        if (null !== $sortBy && \strlen($sortBy) > 0) {
            $commonSearch->setSort([
                $search->getSortBy() => \array_filter([
                    'order' => (empty($search->getSortOrder()) ? 'asc' : $search->getSortOrder()),
                    'missing' => '_last',
                    'unmapped_type' => 'long',
                    'nested_path' => $this->getNestedPath($sortBy, $mapping),
                ]),
            ]);
        }

        return $commonSearch;
    }

    public function getDocument(ContentType $contentType, string $ouuid, ?Environment $environment = null): ElasticsearchDocument
    {
        $index = $environment?->getAlias() ?? $contentType->giveEnvironment()->getAlias();
        $searchQuery = null;

        if ($contentType->hasVersionTags() && null !== $dateToField = $contentType->getVersionDateToField()) {
            $shouldVersion = new BoolQuery();
            $shouldVersion->addMust($this->elasticaService->getTermsQuery(Mapping::VERSION_UUID, [$ouuid]));
            $shouldVersion->addMustNot(new Exists($dateToField));

            $searchQuery = new BoolQuery();
            $searchQuery->setMinimumShouldMatch(1);
            $searchQuery->addShould($shouldVersion);
            $searchQuery->addShould(new Terms('_id', [$ouuid]));
        }

        return $this->elasticaService->getDocument($index, $contentType->getName(), $ouuid, [], [], $searchQuery);
    }

    public function getDocumentByEmsLink(EMSLink $emsLink): ElasticsearchDocument
    {
        $contentType = $this->contentTypeService->giveByName($emsLink->getContentType());

        return $this->getDocument($contentType, $emsLink->getOuuid());
    }

    /**
     * @param array<mixed> $esFilter
     *
     * @return array<mixed>
     */
    private function nestFilter(string $nestedPath, array $esFilter): array
    {
        $path = \explode('.', $nestedPath);

        for ($i = \count($path); $i > 0; --$i) {
            $esFilter = [
                'nested' => [
                    'path' => \implode('.', \array_slice($path, 0, $i)),
                    'query' => $esFilter,
                ],
            ];
        }

        return $esFilter;
    }

    /**
     * @param array<mixed> $mapping
     */
    private function getNestedPath(string $field, ?array $mapping): ?string
    {
        if (!\strpos($field, '.')) {
            return null;
        }

        if (null === $mapping) {
            return null;
        }

        $nestedPath = [];
        $explode = \explode('.', $field);

        foreach ($explode as $field) {
            if (!isset($mapping[$field])) {
                break;
            }

            $fieldMapping = $mapping[$field];

            if ('nested' === ($fieldMapping['type'] ?? null)) {
                $nestedPath[] = $field;
                $mapping = $fieldMapping['properties'] ?? []; // go to nested properties
            } elseif (isset($fieldMapping['fields'])) {
                $mapping = $fieldMapping['fields']; // go to sub fields
            }
        }

        return \implode('.', $nestedPath);
    }

    /**
     * @param string[] $contentTypes
     */
    public function getDefaultSearch(array $contentTypes = []): Search
    {
        $searchRepository = $this->doctrine->getRepository(Search::class);

        $search = null;
        if (1 === \sizeof($contentTypes)) {
            $contentTypeName = \array_values($contentTypes)[0];
            $contentType = $this->contentTypeService->giveByName($contentTypeName);
            $search = $searchRepository->findOneBy(['contentType' => $contentType]);

            if (null === $search && false === $contentType->giveEnvironment()->getManaged()) {
                $search = new Search();
                $search
                    ->setEnvironments([$contentType->giveEnvironment()->getName()])
                    ->setContentTypes([$contentType->getName()]);
            }
        }

        if (!$search instanceof Search) {
            $search = $searchRepository->findOneBy([
                'default' => true,
            ]);
            if ($search instanceof Search and \count($search->getContentTypes()) > 0) {
                $contentTypesNotCovertByTheDefaultSearch = \array_diff($contentTypes, $search->getContentTypes());
                if (\count($contentTypesNotCovertByTheDefaultSearch) > 0) {
                    $search = null;
                }
            }
        }

        if (!$search instanceof Search) {
            $search = new Search();
        }
        $search->setContentTypes($contentTypes);
        if (0 === \count($search->getEnvironments())) {
            $all = [];
            $defaults = [];
            foreach ($this->environmentService->getEnvironments() as $environment) {
                $all[] = $environment->getName();
                if ($environment->getInDefaultSearch()) {
                    $defaults[] = $environment->getName();
                }
            }
            $search->setEnvironments(\count($defaults) > 0 ? $defaults : $all);
        }

        return $search;
    }
}
