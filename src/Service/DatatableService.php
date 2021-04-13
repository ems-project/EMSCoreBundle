<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Form\Data\ElasticaTable;
use Psr\Log\LoggerInterface;

final class DatatableService
{
    private ElasticaService $elasticaService;
    private EnvironmentService $environmentService;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, ElasticaService $elasticaService, EnvironmentService $environmentService)
    {
        $this->elasticaService = $elasticaService;
        $this->logger = $logger;
        $this->environmentService = $environmentService;
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $jsonConfig
     */
    public function generateDatatable(array $environmentNames, array $contentTypeNames, array $jsonConfig): ElasticaTable
    {
        $indexes = $this->convertToIndexes($environmentNames);

        return ElasticaTable::fromConfig($this->elasticaService, $indexes, $contentTypeNames, $jsonConfig);
    }

    /**
     * @param string[] $environmentNames
     * @return string[]
     */
    public function convertToIndexes(array $environmentNames): array
    {
        $indexes = [];
        foreach ($environmentNames as $name) {
            $environment = $this->environmentService->getByName($name);
            if (false === $environment) {
                $this->logger->warning('log.service.datatable.environment-not-found', ['name' => $name]);
                continue;
            }
            $indexes[] = $environment->getAlias();
        }

        return $indexes;
    }
}
