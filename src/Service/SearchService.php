<?php

namespace EMS\CoreBundle\Service;

use Elastica\Query\BoolQuery;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CommonBundle\Search\Search as CommonSearch;

class SearchService
{
    /** @var Mapping */
    private $mapping;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var EnvironmentService */
    private $environmentService;

    public function __construct(Mapping $mapping, ElasticaService $elasticaService, EnvironmentService $environmentService)
    {
        $this->mapping = $mapping;
        $this->elasticaService = $elasticaService;
        $this->environmentService = $environmentService;
    }

    /**
     * @deprecated
     * @return array<mixed>
     */
    public function generateSearchBody(Search $search): array
    {
        @trigger_error("SearchService::generateSearchBody is deprecated use the SearchService::generateSearch method instead", E_USER_DEPRECATED);
        $commonSearch = $this->generateSearch($search);
        $body = [];
        $query = $commonSearch->getQuery();
        if ($query !== null) {
            $body['query'] = $query->toArray();
        }
        $body['sort'] = $commonSearch->getSort();
        return $body;
    }

    public function generateSearch(Search $search): CommonSearch
    {
        $mapping = $this->mapping->getMapping($search->getEnvironments());

        $boolQuery = new BoolQuery();

        foreach ($search->getFilters() as $filter) {
            if (!$esFilter = $filter->generateEsFilter()) {
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
                    $boolQuery->addFilter($esFilter);
                    break;
                default:
                    throw new \RuntimeException('Unexpected operator');
            }
        }

        $indexes = [];
        foreach ($search->getEnvironments() as $environmentName) {
            $environment = $this->environmentService->getByName($environmentName);
            if (!$environment instanceof Environment) {
                throw new \RuntimeException(sprintf('Environment %s not found', $environmentName));
            }
            $indexes[] = $environment->getAlias();
        }

        $commonSearch = new CommonSearch($indexes, $this->elasticaService->filterByContentTypes($boolQuery, $search->getContentTypes()));

        $sortBy = $search->getSortBy();
        if (null != $sortBy && strlen($sortBy) > 0) {
            $commonSearch->setSort([
                $search->getSortBy() => array_filter([
                    'order' => (empty($search->getSortOrder()) ? 'asc' : $search->getSortOrder()),
                    'missing' => '_last' ,
                    'unmapped_type' => 'long',
                    'nested_path' => $this->getNestedPath($sortBy, $mapping),
                ])
            ]);
        }
        return $commonSearch;
    }

    /**
     * @param array<mixed> $esFilter
     * @return array<mixed>
     */
    private function nestFilter(string $nestedPath, array $esFilter): array
    {
        $path = explode('.', $nestedPath);

        for ($i = count($path); $i > 0; --$i) {
            $esFilter = [
                "nested" => [
                    "path" => \implode('.', \array_slice($path, 0, $i)),
                    "query" => $esFilter,
                ]
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

        if ($mapping === null) {
            return null;
        }


        $nestedPath = [];
        $explode = \explode('.', $field);

        foreach ($explode as $field) {
            if (!isset($mapping[$field])) {
                break;
            }

            $fieldMapping = $mapping[$field];

            if ($fieldMapping['type'] == 'nested') {
                $nestedPath[] = $field;
                $mapping = $fieldMapping['properties'] ?? []; //go to nested properties
            } else if (isset($fieldMapping['fields'])) {
                $mapping = $fieldMapping['fields']; //go to sub fields
            }
        }

        return \implode('.', $nestedPath);
    }
}
