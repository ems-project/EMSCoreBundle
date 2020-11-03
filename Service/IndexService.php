<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Elasticsearch\Client;
use Psr\Log\LoggerInterface;

final class IndexService
{
    /** @var AliasService */
    private $aliasService;
    /** @var Client */
    private $client;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(AliasService $aliasService, Client $client, LoggerInterface $logger)
    {
        $this->aliasService = $aliasService;
        $this->client = $client;
        $this->logger = $logger;
    }

    public function deleteOrphanIndexes(): void
    {
        $this->aliasService->build();
        foreach ($this->aliasService->getOrphanIndexes() as $index) {
            try {
                $this->client->indices()->delete([
                    'index' => $index['name'],
                ]);
                $this->logger->notice('log.index.delete_orphan_index', [
                    'index_name' => $index['name'],
                ]);
            } catch (\RuntimeException $e) {
                $this->logger->notice('log.index.index_not_found', [
                    'index_name' => $index['name'],
                ]);
            }
        }
    }
}
