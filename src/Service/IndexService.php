<?php

namespace EMS\CoreBundle\Service;

use Elastica\Client;
use Elasticsearch\Endpoints\Index;
use Elasticsearch\Endpoints\Indices\Aliases\Update;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use Psr\Log\LoggerInterface;

final class IndexService
{
    /** @var AliasService */
    private $aliasService;
    /** @var Client */
    private $client;
    /** @var LoggerInterface */
    private $logger;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var Mapping */
    private $mapping;

    public function __construct(AliasService $aliasService, Client $client, ContentTypeService $contentTypeService, LoggerInterface $logger, Mapping $mapping)
    {
        $this->aliasService = $aliasService;
        $this->client = $client;
        $this->logger = $logger;
        $this->contentTypeService = $contentTypeService;
        $this->mapping = $mapping;
    }

    public function deleteOrphanIndexes(): void
    {
        $this->aliasService->build();
        foreach ($this->aliasService->getOrphanIndexes() as $index) {
            $this->deleteIndex($index['name']);
        }
    }

    public function deleteIndex(string $indexName): void
    {
        try {
            $this->client->getIndex($indexName)->delete();
            $this->logger->notice('log.index.delete_orphan_index', [
                'index_name' => $indexName,
            ]);
        } catch (\RuntimeException $e) {
            $this->logger->notice('log.index.index_not_found', [
                'index_name' => $indexName,
            ]);
        }
    }

    public function indexRevision(Revision $revision, ?Environment $environment = null): void
    {
        $contentType = $revision->getContentType();
        if (null === $contentType) {
            throw new \RuntimeException('Unexpected null content type');
        }
        if (null === $environment) {
            $environment = $contentType->getEnvironment();
        }
        if (null === $environment) {
            throw new \RuntimeException('Unexpected null environment');
        }

        $endpoint = new Index();
        $endpoint->setType($this->mapping->getTypeName($contentType));
        $endpoint->setIndex($this->contentTypeService->getIndex($contentType, $environment));
        $endpoint->setBody($revision->getRawData());
        $endpoint->setID($revision->getOuuid());
        $this->client->requestEndpoint($endpoint);
    }

    /**
     * @param string[] $indexesToAdd
     * @param string[] $indexesToRemove
     */
    public function updateAlias(string $aliasName, array $indexesToRemove, array $indexesToAdd): void
    {
        $action = [];
        foreach ($indexesToRemove as $index) {
            $action[] = [
                'remove' => [
                    'index' => $index,
                    'alias' => $aliasName,
                ],
            ];
        }
        foreach ($indexesToAdd as $index) {
            $action[] = [
                'add' => [
                    'index' => $index,
                    'alias' => $aliasName,
                ],
            ];
        }
        $endpoint = new Update();
        $endpoint->setBody(['actions' => $action]);
        $this->client->requestEndpoint($endpoint);
    }
}
