<?php

namespace EMS\CoreBundle\Elasticsearch;

use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\Mapping;
use Psr\Log\LoggerInterface;

class Indexer
{
    /** @var LoggerInterface */
    private $logger;
    /** @var IndexService */
    private $indexService;
    /** @var Mapping */
    private $mapping;
    /** @var AliasService */
    private $aliasService;

    public function __construct(IndexService $indexService, LoggerInterface $logger, Mapping $mapping, AliasService $aliasService)
    {
        $this->logger = $logger;
        $this->indexService = $indexService;
        $this->mapping = $mapping;
        $this->aliasService = $aliasService;
    }

    public function setLogger(LoggerInterface $logger): Indexer
    {
        $this->logger = $logger;

        return $this;
    }

    public function exists(string $name): bool
    {
        return $this->indexService->hasIndex($name);
    }

    public function delete(string $name): void
    {
        $this->indexService->deleteIndex($name);
        $this->logger->warning('Deleted index {index}', ['index' => $name]);
    }

    /**
     * @param string $name
     * @param array<mixed> $settings
     * @param array<mixed> $mappings
     */
    public function create(string $name, array $settings, array $mappings): void
    {
        $body = \array_filter(['settings' => $settings, 'mappings' => $mappings]);
        $this->mapping->createIndex($name, $body);
        $this->logger->info('Created index {index}', ['index' => $name]);
    }

    /**
     * @param string $name
     * @param array<mixed> $mappings
     * @param string $type
     */
    public function update(string $name, array $mappings, string $type): void
    {
        $this->mapping->updateMapping($name, $mappings, $type);
        $this->logger->info('Update index {index}\'s mapping', ['index' => $name]);
    }

    /**
     * When clean is true, the old index will be removed!
     * Use oldIndexRegex when the alias has multiple indexes attached and you only want to switch aggainst a regex.
     */
    public function atomicSwitch(string $alias, string $newIndex, string $removeRegex = null, bool $clean = false): void
    {
        $indexesToAdd = [$newIndex];
        $indexesToRemove = [];
        $indexesToDelete = [];

        if (!empty($removeRegex)) {
            foreach ($this->indexService->getIndexesByAlias($alias) as $index) {
                if (!\preg_match($removeRegex, $index)) {
                    continue;
                }
                $indexesToRemove[] = $index;
                $indexesToDelete[] = $index;
            }
        }

        $this->aliasService->updateAlias($alias, ['add' => $indexesToAdd, 'remove' => $indexesToRemove]);
        $this->logger->info('Alias {alias} is now pointing to {index}', ['alias' => $alias, 'index' => $newIndex]);
        if (!$clean) {
            return;
        }
        foreach ($indexesToDelete as $index) {
            $this->indexService->deleteIndex($index);
        }
    }

    /**
     * @param string $indexName
     * @return array<string>
     */
    public function getAliasesByIndex(string $indexName): array
    {
        return $this->indexService->getAliasesByIndex($indexName);
    }
}
