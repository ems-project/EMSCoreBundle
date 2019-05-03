<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;

class SearchService
{
    /** @var Mapping */
    private $mapping;

    public function __construct(Mapping $mapping)
    {
        $this->mapping = $mapping;
    }
    
    public function generateSearchBody(Search $search)
    {
        $mapping = $this->mapping->getMapping($search->getEnvironments());

        $body = [];

        /** @var SearchFilter $filter */
        foreach ($search->getFilters() as $filter) {
            if (!$esFilter = $filter->generateEsFilter()) {
                continue;
            }

            if ($nestedPath = $this->getNestedPath($filter->getField(), $mapping)) {
                $esFilter = $this->nestFilter($nestedPath, $esFilter);
            }

            $body["query"]["bool"][$filter->getBooleanClause()][] = $esFilter;
        }

        if (isset($body["query"]["bool"]['should'])) {
            $body["query"]["bool"]['minimum_should_match'] = 1;
        }

        if (null != $search->getSortBy() && strlen($search->getSortBy()) > 0) {
            $body["sort"] = [
                $search->getSortBy() => array_filter([
                    'order' => (empty($search->getSortOrder())?'asc': $search->getSortOrder()),
                    'missing' => '_last',
                    'unmapped_type' => 'long',
                    'nested_path' => $this->getNestedPath($search->getSortBy(), $mapping),
                ])
            ];
        }
        return $body;
    }

    private function nestFilter(string $nestedPath, array $esFilter): array
    {
        $path = explode('.', $nestedPath);

        for ($i=count($path); $i > 0; --$i) {
            $esFilter = [
                "nested" => [
                    "path" => \implode('.', \array_slice($path, 0, $i)),
                    "query" => $esFilter,
                ]
            ];
        }

        return $esFilter;
    }

    private function getNestedPath(string $field, array $mapping): ?string
    {
        if (!\strpos($field, '.')) {
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
                $mapping = $fieldMapping['properties']; //go to nested properties
            } else if (isset($fieldMapping['fields'])) {
                $mapping = $fieldMapping['fields']; //go to sub fields
            }
        }

        return \implode('.', $nestedPath);
    }
}
