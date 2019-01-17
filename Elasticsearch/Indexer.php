<?php

namespace EMS\CoreBundle\Elasticsearch;

use Elasticsearch\Client;
use EMS\CoreBundle\Elasticsearch\Index\Mapping;
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

    public function new(string $name, Settings $settings, Mapping $mapping, bool $delete = false): void
    {
        $params = ['index' => $name];
        $indices = $this->client->indices();
        $exists = $indices->exists($params);

        if ($exists && $delete) {
            $indices->delete($params);
            $this->logger->info('Deleted index {index}', $params);
        } elseif ($exists) {
            $this->logger->info('Index {index} already exists!', $params);
            return;
        }

        $body = [];
        if (!$settings->isEmpty()) {
            $body['settings'] = $settings->toArray();
        }
        if (!$mapping->isEmpty()) {
            $body['mapping'] = $mapping->toArray();
        }

        $indices->create(['index' => $name, 'body' => $body,]);
        $this->logger->info('Created index {index}', $params);
    }
}