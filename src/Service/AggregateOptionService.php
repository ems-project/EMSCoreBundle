<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elastica\Aggregation\AbstractAggregation;
use Elastica\Aggregation\Terms;
use EMS\CommonBundle\Elasticsearch\Aggregation\ElasticaAggregation;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\AggregateOption;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class AggregateOptionService extends EntityService
{
    /** @var string */
    const CONTENT_TYPES_AGGREGATION = 'types';
    /** @var string */
    const INDEXES_AGGREGATION = 'indexes';
    /** @var ElasticaService */
    private $elasticaService;

    public function __construct(Registry $doctrine, LoggerInterface $logger, TranslatorInterface $translator, ElasticaService $elasticaService)
    {
        parent::__construct($doctrine, $logger, $translator);
        $this->elasticaService = $elasticaService;
    }

    protected function getRepositoryIdentifier()
    {
        return 'EMSCoreBundle:AggregateOption';
    }
    
    protected function getEntityName()
    {
        return 'Aggregate Option';
    }

    /**
     * @return AbstractAggregation[]
     */
    public function getAllAggregations(): array
    {
        $contentTypeField = '_type';
        if (\version_compare($this->elasticaService->getVersion(), '6.0') >= 0) {
            $contentTypeField = EMSSource::FIELD_CONTENT_TYPE;
        }
        $contentTypeAggregation = new Terms(self::CONTENT_TYPES_AGGREGATION);
        $contentTypeAggregation->setSize(15);
        $contentTypeAggregation->setField($contentTypeField);

        $indexAggregation = new Terms(self::INDEXES_AGGREGATION);
        $indexAggregation->setSize(15);
        $indexAggregation->setField('_index');

        $aggregations = [$contentTypeAggregation, $indexAggregation];

        foreach ($this->getAll() as $id => $option) {
            if (!$option instanceof AggregateOption) {
                throw new \RuntimeException('Unexpected AggregateOption object');
            }
            $aggregations[] = $this->parseAggregation(sprintf('agg_%s', $id), $option->getConfigDecoded());
        }
        return $aggregations;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function parseAggregation(string $name, array $config): AbstractAggregation
    {
        $aggregation = new ElasticaAggregation($name);
        if (\count($config) !== 1) {
            throw new \RuntimeException('Unexpected aggregation with multiple, or zero, basename');
        }
        foreach ($config as $basename => $param) {
            $aggregation->setConfig($basename, $param);
        }
        return $aggregation;
    }
}
