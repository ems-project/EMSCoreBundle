<?php

namespace EMS\CoreBundle\Service;

use Elasticsearch\Client;
use Psr\Log\LoggerInterface;

final class IndexService
{
    /** @var AliasService */
    private $aliasService;
    /** @var Client */
    private $elasticSearchClient;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(AliasService $aliasService, Client $elasticSearchClient, LoggerInterface $logger)
    {
        $this->aliasService = $aliasService;
        $this->elasticSearchClient = $elasticSearchClient;
        $this->logger = $logger;
    }

    public function deleteOrphanIndexes(): void
    {
        $this->aliasService->build();
        foreach ($this->aliasService->getOrphanIndexes() as $index) {
            try {
                $this->elasticSearchClient->indices()->delete([
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