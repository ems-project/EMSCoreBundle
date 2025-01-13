<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Form\Data\ElasticaTable;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

final readonly class DatatableService
{
    private const string CONFIG = 'config';
    private const string ALIASES = 'aliases';
    private const string CONTENT_TYPES = 'contentTypes';

    public function __construct(
        private LoggerInterface $logger,
        private RouterInterface $router,
        private ElasticaService $elasticaService,
        private StorageManager $storageManager,
        private EnvironmentService $environmentService,
        private string $templateNamespace
    ) {
    }

    /**
     * @param string[]             $environmentNames
     * @param list<string>         $contentTypeNames
     * @param array<string, mixed> $options
     */
    public function generateDatatable(array $environmentNames, array $contentTypeNames, array $options): ElasticaTable
    {
        $aliases = $this->convertToAliases($environmentNames);
        $hashConfig = $this->saveConfig($options, $aliases, $contentTypeNames);

        return ElasticaTable::fromConfig($this->templateNamespace, $this->elasticaService, $this->getAjaxUrl($hashConfig, $options[ElasticaTable::PROTECTED] ?? true), $aliases, $contentTypeNames, $options);
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public function getExcelPath(array $environmentNames, array $contentTypeNames, array $options): string
    {
        $route = 'ems_core_datatable_excel_elastica';
        if (false === ($options['protected'] ?? true)) {
            $route = 'ems_core_datatable_excel_elastica_public';
        }

        return $this->getRoutePath($route, $environmentNames, $contentTypeNames, $options);
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public function getCsvPath(array $environmentNames, array $contentTypeNames, array $options): string
    {
        $route = 'ems_core_datatable_csv_elastica';
        if (false === ($options['protected'] ?? true)) {
            $route = 'ems_core_datatable_csv_elastica_public';
        }

        return $this->getRoutePath($route, $environmentNames, $contentTypeNames, $options);
    }

    public function generateDatatableFromHash(string $hashConfig): ElasticaTable
    {
        $config = $this->parsePersistedConfig($this->storageManager->getContents($hashConfig));

        return ElasticaTable::fromConfig($this->templateNamespace, $this->elasticaService, $this->getAjaxUrl($hashConfig, $config[self::CONFIG][ElasticaTable::PROTECTED] ?? true), $config[self::ALIASES], $config[self::CONTENT_TYPES], $config[self::CONFIG]);
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
     * @return array{contentTypes: list<string>, aliases: string[], config: array<mixed>}
     */
    private function parsePersistedConfig(string $jsonConfig): array
    {
        try {
            $parameters = Json::decode($jsonConfig);
        } catch (\Throwable) {
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
        /** @var array{contentTypes: list<string>, aliases: string[], config: array<mixed>} $resolvedParameter */
        $resolvedParameter = $resolver->resolve($parameters);

        return $resolvedParameter;
    }

    public function getAjaxUrl(string $hashConfig, bool $protected = true): string
    {
        if ($protected) {
            $route = 'ems_core_datatable_ajax_elastica';
        } else {
            $route = 'ems_core_datatable_ajax_elastica_public';
        }

        return $this->router->generate($route, ['hashConfig' => $hashConfig]);
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
