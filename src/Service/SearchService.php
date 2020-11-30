<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Common\Document;
use EMS\CommonBundle\Elasticsearch\Document\Document as ElasticsearchDocument;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Elasticsearch\Exception\NotSingleResultException;
use EMS\CommonBundle\Search\Search as CommonSearch;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;

class SearchService
{
    /** @var Mapping */
    private $mapping;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var Registry */
    private $doctrine;

    public function __construct(Registry $doctrine, Mapping $mapping, ElasticaService $elasticaService, EnvironmentService $environmentService, ContentTypeService $contentTypeService)
    {
        $this->mapping = $mapping;
        $this->elasticaService = $elasticaService;
        $this->environmentService = $environmentService;
        $this->contentTypeService = $contentTypeService;
        $this->doctrine = $doctrine;
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
            $body['query'] = $query->toArray();
        }
        $body['sort'] = $commonSearch->getSort();

        return $body;
    }

    public function generateSearch(Search $search): CommonSearch
    {
        $mapping = $this->mapping->getMapping($search->getEnvironments());

        $boolQuery = $this->elasticaService->getBoolQuery();

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
                throw new \RuntimeException(\sprintf('Environment %s not found', $environmentName));
            }
            $indexes[] = $environment->getAlias();
        }

        $commonSearch = new CommonSearch($indexes, $this->elasticaService->filterByContentTypes($boolQuery, $search->getContentTypes()));

        $sortBy = $search->getSortBy();
        if (null != $sortBy && \strlen($sortBy) > 0) {
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
        if (null === $environment) {
            $environment = $contentType->getEnvironment();
        }
        if (null === $environment) {
            throw new \RuntimeException('Unexpected null environment');
        }
        $index = $this->contentTypeService->getIndex($contentType, $environment);
        $search = $this->elasticaService->generateTermsSearch([$index], '_id', [$ouuid], [$contentType->getName()]);
        try {
            return $this->elasticaService->singleSearch($search);
        } catch (NotSingleResultException $e) {
            if (0 === $e->getTotal()) {
                throw new NotFoundException();
            }
            throw $e;
        }
    }

    public function get(Environment $environment, ContentType $contentType, string $ouuid): Document
    {
        $document = $this->getDocument($contentType, $ouuid, $environment);

        return new Document($document->getContentType(), $document->getId(), $document->getSource());
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

            if ('nested' == $fieldMapping['type']) {
                $nestedPath[] = $field;
                $mapping = $fieldMapping['properties'] ?? []; //go to nested properties
            } elseif (isset($fieldMapping['fields'])) {
                $mapping = $fieldMapping['fields']; //go to sub fields
            }
        }

        return \implode('.', $nestedPath);
    }

    /**
     * @param string[] $contentTypes
     */
    public function getDefaultSearch(array $contentTypes = []): Search
    {
        $searchRepository = $this->doctrine->getRepository('EMSCoreBundle:Form\Search');

        $search = null;
        if (1 === \sizeof($contentTypes)) {
            $search = $searchRepository->findOneBy([
                'contentType' => \array_pop($contentTypes),
            ]);
        }

        if (!$search instanceof Search) {
            $search = $searchRepository->findOneBy([
                'default' => true,
            ]);
        }

        if (!$search instanceof Search) {
            $search = new Search();
            $filter = new SearchFilter();
            $filter->setBooleanClause('must');
            $filter->setOperator('match_and');
        } else {
            $search->resetFilters();
        }
        if (\count($contentTypes) > 0) {
            $search->setContentTypes($contentTypes);
        }

        return $search;
    }
}
