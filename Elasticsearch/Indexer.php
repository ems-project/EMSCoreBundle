<?php

namespace EMS\CoreBundle\Elasticsearch;

use Elasticsearch\Client;
use EMS\CoreBundle\Elasticsearch\Index\Mappings;
use EMS\CoreBundle\Elasticsearch\Index\Settings;
use Psr\Log\LoggerInterface;

class Indexer
{
    /** @var Client */
    private $client;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface $logger): Indexer
    {
        $this->logger = $logger;
        return $this;
    }

    public function exists(string $name): bool
    {
        return $this->client->indices()->exists(['index' => $name]);
    }

    public function delete(string $name): void
    {
        $params = ['index' => $name];
        $this->client->indices()->delete($params);
        $this->logger->info('Deleted index {index}', $params);
    }

    public function create(string $name, Settings $settings, Mappings $mappings): string
    {
        $body = [];
        if (!$settings->isEmpty()) {
            $body['settings'] = $settings->toArray();
        }
        if (!$mappings->isEmpty()) {
            $body['mappings'] = $mappings->toArray();
        }

        $this->client->indices()->create(['index' => $name, 'body' => $body,]);
        $this->logger->info('Created index {index}', ['index' => $name]);

        return $name;
    }

    public function atomicSwitch(string $alias, string $index): void
    {
        $params = ['name' => $alias];
        $indices = $this->client->indices();
        $actions = [['add' => ['index' => $index, 'alias' => $alias]]];

        if ($indices->existsAlias($params)) {
            $infoAlias = $indices->getAlias($params);

            foreach (array_keys($infoAlias) as $oldIndex) {
                $actions[] = ['remove' => ['index' => $oldIndex, 'alias' => $alias]];
            }
        }

        $indices->updateAliases(['body' => ['actions' => $actions]]);

        $this->logger->info('Alias {alias} is now pointing to {index}', ['alias' => $alias, 'index' => $index]);
    }
}