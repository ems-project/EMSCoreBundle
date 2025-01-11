<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Elasticsearch\Aggregation\ElasticaAggregation;
use EMS\CoreBundle\Entity\AggregateOption;

class AggregateOptionService extends EntityService
{
    final public const string CONTENT_TYPES_AGGREGATION = 'types';
    final public const string INDEXES_AGGREGATION = 'indexes';

    #[\Override]
    protected function getRepositoryIdentifier(): string
    {
        return AggregateOption::class;
    }

    #[\Override]
    protected function getEntityName(): string
    {
        return 'Aggregate Option';
    }

    /**
     * @return ElasticaAggregation[]
     */
    public function getAllAggregations(): array
    {
        $aggregations = [];

        foreach ($this->getAll() as $id => $option) {
            if (!$option instanceof AggregateOption) {
                throw new \RuntimeException('Unexpected AggregateOption object');
            }
            $aggregations[] = $this->parseAggregation(\sprintf('agg_%s', $id), $option->getConfigDecoded());
        }

        return $aggregations;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function parseAggregation(string $name, array $config): ElasticaAggregation
    {
        $aggregation = new ElasticaAggregation($name);
        if (1 !== \count($config)) {
            throw new \RuntimeException('Unexpected aggregation with multiple, or zero, basename');
        }
        foreach ($config as $basename => $param) {
            $aggregation->setConfig($basename, $param);
        }

        return $aggregation;
    }
}
