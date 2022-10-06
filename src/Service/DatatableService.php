<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Form\Data\ElasticaTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

final class DatatableService
{
    private const CONFIG = 'config';
    private const ALIASES = 'aliases';
    private const CONTENT_TYPES = 'contentTypes';
    private ElasticaService $elasticaService;
    private EnvironmentService $environmentService;
    private LoggerInterface $logger;
    private RouterInterface $router;
    private StorageManager $storageManager;

    public function __construct(LoggerInterface $logger, RouterInterface $router, ElasticaService $elasticaService, StorageManager $storageManager, EnvironmentService $environmentService)
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
     * @param array<string, mixed> $options
     */
    public function generateDatatable(array $environmentNames, array $contentTypeNames, array $options): ElasticaTable
    {
        $aliases = $this->convertToAliases($environmentNames);
        $hashConfig = $this->saveConfig($options, $aliases, $contentTypeNames);

        return ElasticaTable::fromConfig($this->elasticaService, $this->getAjaxUrl($hashConfig), $aliases, $contentTypeNames, $options);
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public function getExcelPath(array $environmentNames, array $contentTypeNames, array $options): string
    {
        return $this->getRoutePath('ems_core_datatable_excel_elastica', $environmentNames, $contentTypeNames, $options);
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public function getCsvPath(array $environmentNames, array $contentTypeNames, array $options): string
    {
        return $this->getRoutePath('ems_core_datatable_csv_elastica', $environmentNames, $contentTypeNames, $options);
    }

    public function generateDatatableFromHash(string $hashConfig): ElasticaTable
    {
        $config = $this->parsePersistedConfig($this->storageManager->getContents($hashConfig));

        return ElasticaTable::fromConfig($this->elasticaService, $this->getAjaxUrl($hashConfig), $config[self::ALIASES], $config[self::CONTENT_TYPES], $config[self::CONFIG]);
    }

    /**
     * @param string[] $environmentNames
     *
     * @return string[]
     */
    public function convertToAliases(array $environmentNames): array
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

    /**
     * @return array{contentTypes: string[], aliases: string[], config: array<mixed>}
     */
    private function parsePersistedConfig(string $jsonConfig): array
    {
        $parameters = \json_decode($jsonConfig, true);
        if (!\is_array($parameters)) {
            throw new \RuntimeException('Unexpected JSON config');
        }

        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                self::CONTENT_TYPES => [],
                self::ALIASES => [],
                self::CONFIG => [],
            ])
            ->setAllowedTypes(self::CONTENT_TYPES, ['array'])
            ->setAllowedTypes(self::ALIASES, ['array'])
            ->setAllowedTypes(self::CONFIG, ['array'])
        ;
        /** @var array{contentTypes: string[], aliases: string[], config: array<mixed>} $resolvedParameter */
        $resolvedParameter = $resolver->resolve($parameters);

        return $resolvedParameter;
    }

    public function getAjaxUrl(string $hashConfig): string
    {
        return $this->router->generate('ems_core_datatable_ajax_elastica', ['hashConfig' => $hashConfig]);
    }

    /**
     * @param array<mixed> $options
     * @param string[]     $aliases
     * @param string[]     $contentTypeNames
     */
    private function saveConfig(array $options, array $aliases, array $contentTypeNames): string
    {
        return $this->storageManager->saveConfig([
            self::CONFIG => $options,
            self::ALIASES => $aliases,
            self::CONTENT_TYPES => $contentTypeNames,
        ]);
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    private function getRoutePath(string $route, array $environmentNames, array $contentTypeNames, array $options): string
    {
        $aliases = $this->convertToAliases($environmentNames);
        $hashConfig = $this->saveConfig($options, $aliases, $contentTypeNames);

        return $this->router->generate($route, ['hashConfig' => $hashConfig]);
    }
}
