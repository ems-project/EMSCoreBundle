<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Elasticsearch\Aggregation\ElasticaAggregation;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\AggregateOption;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AggregateOptionService extends EntityService
{
    public const CONTENT_TYPES_AGGREGATION = 'types';
    public const INDEXES_AGGREGATION = 'indexes';
    private ElasticaService $elasticaService;

    public function __construct(
        Registry $doctrine,
        LoggerInterface $logger,
        TranslatorInterface $translator,
        ElasticaService $elasticaService
    ) {
        parent::__construct($doctrine, $logger, $translator);
        $this->elasticaService = $elasticaService;
    }

    public function getContentTypeField(): string
    {
        if (\version_compare($this->elasticaService->getVersion(), '6.0') >= 0) {
            return EMSSource::FIELD_CONTENT_TYPE;
        }

        return '_type';
    }

    protected function getRepositoryIdentifier(): string
    {
        return AggregateOption::class;
    }

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
