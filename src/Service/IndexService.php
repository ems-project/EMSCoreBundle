<?php

namespace EMS\CoreBundle\Service;

use Elastica\Client;
use Elasticsearch\Endpoints\Index;
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

    public function indexRevision(Revision $revision, ?Environment $environment = null): bool
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

        $objectArray = $revision->getRawData();
        $objectArray[Mapping::PUBLISHED_DATETIME_FIELD] = (new \DateTime())->format(\DateTime::ISO8601);

        $endpoint = new Index();
        $endpoint->setType($this->mapping->getTypeName($contentType));
        $endpoint->setIndex($this->contentTypeService->getIndex($contentType, $environment));
        $endpoint->setBody($objectArray);
        $endpoint->setID($revision->getOuuid());
        $result = $this->client->requestEndpoint($endpoint)->getData();

        return \intval($result['_shards']['successful'] ?? 0) > 0;
    }

    /**
     * @param string[] $indexesToAdd
     * @param string[] $indexesToRemove
     */
    public function updateAlias(string $aliasName, array $indexesToRemove, array $indexesToAdd): void
    {
        $actions = [];
        if (\count($indexesToRemove) > 0) {
            $actions['remove'] = $indexesToRemove;
        }
        if (\count($indexesToAdd) > 0) {
            $actions['add'] = $indexesToAdd;
        }
        $this->aliasService->updateAlias($aliasName, $actions);
    }
}
