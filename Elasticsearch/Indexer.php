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
        $this->logger->warning('Deleted index {index}', $params);
    }

    public function create(string $name, Settings $settings, Mappings $mappings): void
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
    }

    /**
     * When clean is true, the old index will be removed!
     * Use oldIndexRegex when the alias has multiple indexes attached and you only want to switch aggainst a regex.
     */
    public function atomicSwitch(string $alias, string $newIndex, string $removeRegex = null, bool $clean = false): void
    {
        $indices = $this->client->indices();
        $actions = [['add' => ['index' => $newIndex, 'alias' => $alias]]];
        $delete = [];

        if ($removeRegex && $indices->existsAlias(['name' => $alias])) {
            $infoAlias = $indices->getAlias(['name' => $alias]);

            foreach (array_keys($infoAlias) as $oldIndex) {
                if (!preg_match($removeRegex, $oldIndex)) {
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

        array_map([$this, 'delete'], $delete);
    }
}
