<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elastica\Query\BoolQuery;
use Elastica\Query\Term;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Search\Search as CommonSearch;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\ContentType\Version\VersionFields;
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

    public function generateSearch(Search $search): CommonSearch
    {
        $environments = \array_filter(
            \array_map($this->environmentService->giveByName(...), $search->getEnvironments()),
            fn (Environment $e) => $this->elasticaService->hasIndex($e->getAlias())
        );

        $mapping = $this->mapping->getMapping(...$environments);

        $boolQuery = $this->elasticaService->getBoolQuery();

        foreach ($search->getFilters() as $filter) {
            if (null === $esFilter = $filter->generateEsFilter()) {
                continue;
            }

            if ($filter->getField() && ($nestedPath = $this->getNestedFieldPath($filter->getField(), $mapping))) {
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
                    $boolQuery->addFilter(new BoolQuery()->addMust($esFilter));
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
        if (null !== $sortBy && '' !== $sortBy) {
            $commonSearch->setSort([
                $sortBy => \array_filter([
                    'order' => (empty($search->getSortOrder()) ? 'asc' : $search->getSortOrder()),
                    'missing' => '_last',
                    'unmapped_type' => 'long',
                    'nested' => $this->getNestedFieldPath($sortBy, $mapping),
                ]),
            ]);
        }

        return $commonSearch;
    }

    public function getDocument(ContentType $contentType, string $ouuid, ?Environment $environment = null): Document
    {
        $index = $environment?->getAlias() ?? $contentType->giveEnvironment()->getAlias();
        $searchQuery = null;

        $versioning = $contentType->getVersioning();
        if ($versioning->enabled() && null !== $dateFromField = $versioning->field(VersionFields::DATE_FROM)) {
            $searchLatestVersion = new CommonSearch([$index], new Term([Mapping::VERSION_UUID => $ouuid]));
            $searchLatestVersion->setSize(1);
            $searchLatestVersion->setSort([$dateFromField => ['order' => 'desc']]);

            $searchLatestResults = $this->elasticaService->search($searchLatestVersion)->getResults();
            if (isset($searchLatestResults[0])) {
                return Document::fromResult($searchLatestResults[0]);
            }
        }

        return $this->elasticaService->getDocument($index, $contentType->getName(), $ouuid, [], [], $searchQuery);
    }

    public function getDocumentByEmsLink(EMSLink $emsLink): Document
    {
        $contentType = $this->contentTypeService->giveByName($emsLink->getContentType());

        return $this->getDocument($contentType, $emsLink->getOuuid());
    }

    /**
     * @param array{'path': string} $nested
     * @param array<mixed>          $esFilter
     *
     * @return array<mixed>
     */
    private function nestFilter(array $nested, array $esFilter): array
    {
        $path = \explode('.', $nested['path']);

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
     *
     * @return ?array{path: string}
     */
    private function getNestedFieldPath(string $field, ?array $mapping): ?array
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

        return \count($nestedPath) > 0 ? ['path' => \implode('.', $nestedPath)] : null;
    }

    /**
     * @param string[] $contentTypes
     */
    public function getDefaultSearch(array $contentTypes = []): Search
    {
        $searchRepository = $this->doctrine->getRepository(Search::class);

        $search = null;
        if (1 === \count($contentTypes)) {
            $contentTypeName = \array_first($contentTypes);
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
            if ($search instanceof Search && \count($search->getContentTypes()) > 0) {
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
