<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Elasticsearch;

use Elasticsearch\Client;
use EMS\CommonBundle\Elasticsearch\Factory;
use Psr\Log\LoggerInterface;

class Indexer
{
    /** @var Factory */
    private $factory;
    /** @var array */
    private $options;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(Factory $factory, array $options, LoggerInterface $logger)
    {
        $this->factory = $factory;
        $this->options = $options;
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface $logger): Indexer
    {
        $this->logger = $logger;

        return $this;
    }

    public function exists(string $name): bool
    {
        return $this->getClient()->indices()->exists(['index' => $name]);
    }

    public function delete(string $name): void
    {
        $params = ['index' => $name];
        $this->getClient()->indices()->delete($params);
        $this->logger->warning('Deleted index {index}', $params);
    }

    public function create(string $name, array $settings, array $mappings): void
    {
        $body = \array_filter(['settings' => $settings, 'mappings' => $mappings]);

        $this->getClient()->indices()->create(['index' => $name, 'body' => $body]);
        $this->logger->info('Created index {index}', ['index' => $name]);
    }

    public function update(string $name, array $mappings, string $type): void
    {
        $this->getClient()->indices()->putMapping(['index' => $name, 'body' => $mappings, 'type' => $type]);
        $this->logger->info('Update index {index}\'s mapping', ['index' => $name]);
    }

    /**
     * When clean is true, the old index will be removed!
     * Use oldIndexRegex when the alias has multiple indexes attached and you only want to switch aggainst a regex.
     */
    public function atomicSwitch(string $alias, string $newIndex, string $removeRegex = null, bool $clean = false): void
    {
        $indices = $this->getClient()->indices();
        $actions = [['add' => ['index' => $newIndex, 'alias' => $alias]]];
        $delete = [];

        if ($removeRegex && $indices->existsAlias(['name' => $alias])) {
            $infoAlias = $indices->getAlias(['name' => $alias]);

            foreach (\array_keys($infoAlias) as $oldIndex) {
                if (!\preg_match($removeRegex, $oldIndex)) {
                    continue;
                }

                $actions[] = ['remove' => ['index' => $oldIndex, 'alias' => $alias]];

                if ($clean) {
                    $delete[] = $oldIndex;
                }
            }
        }

        $indices->updateAliases(['body' => ['actions' => $actions]]);
        $this->logger->info('Alias {alias} is now pointing to {index}', ['alias' => $alias, 'index' => $newIndex]);

        \array_map([$this, 'delete'], $delete);
    }

    public function getAliasesByIndex(string $indexName): array
    {
        return $this->getClient()->indices()->getAlias(['index' => $indexName]);
    }

    private function getClient(): Client
    {
        return $this->factory->fromConfig($this->options);
    }
}
