<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Form\Data\ElasticaTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

final class DatatableService
{
    private ElasticaService $elasticaService;
    private EnvironmentService $environmentService;
    private LoggerInterface $logger;
    private RouterInterface $router;
    private StorageManager $storageManager;

    public function __construct(LoggerInterface $logger, ElasticaService $elasticaService, EnvironmentService $environmentService, RouterInterface $router, StorageManager $storageManager)
    {
        $this->elasticaService = $elasticaService;
        $this->logger = $logger;
        $this->environmentService = $environmentService;
        $this->router = $router;
        $this->storageManager = $storageManager;
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $jsonConfig
     */
    public function generateDatatable(array $environmentNames, array $contentTypeNames, array $jsonConfig): ElasticaTable
    {
        $indexes = $this->convertToIndexes($environmentNames);
        $hashConfig = $this->storageManager->saveConfig([
            'config' => $jsonConfig,
            'aliases' => $indexes,
            'contentTypes' => $contentTypeNames,
        ]);
        $ajaxUrl = $this->router->generate('ems_core_datatable_ajax_elastica', ['hashConfig' => $hashConfig]);

        return ElasticaTable::fromConfig($this->elasticaService, $ajaxUrl, $indexes, $contentTypeNames, $jsonConfig);
    }

    /**
     * @param string[] $environmentNames
     *
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
