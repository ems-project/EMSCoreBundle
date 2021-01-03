<?php

namespace EMS\CoreBundle\Service;

use Elastica\Exception\ResponseException;
use Elasticsearch\Endpoints\Index;
use Elasticsearch\Endpoints\Indices\Alias\Get;
use Elasticsearch\Endpoints\Indices\Exists;
use EMS\CommonBundle\Elasticsearch\Client;
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
        if ($revision->hasOuuid()) {
            $endpoint->setID($revision->getOuuid());
        }
        $result = $this->client->requestEndpoint($endpoint)->getData();

        if (!$revision->hasOuuid()) {
            $revision->setOuuid($result['_id']);
        }

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

    public function delete(Revision $revision, ?Environment $environment = null): void
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
        $index = $this->contentTypeService->getIndex($contentType, $environment);
        $path = $this->mapping->getTypePath($contentType->getName());
        $this->client->deleteIds([$revision->getOuuid()], $index, $path);
    }

    public function hasIndex(string $name): bool
    {
        $endpoint = new Exists();
        $endpoint->setIndex($name);
        $result = $this->client->requestEndpoint($endpoint);

        return $result->isOk();
    }

    /**
     * @return string[]
     */
    public function getIndexesByAlias(?string $alias): array
    {
        return \array_keys($this->getAliases($alias));
    }

    /**
     * @return string[]
     */
    public function getAliasesByIndex(?string $indexName): array
    {
        $aliases = [];
        foreach ($this->getAliases($indexName) as $index) {
            $aliases = \array_merge($aliases, \array_keys($index['aliases'] ?? []));
        }

        return $aliases;
    }

    /**
     * @return array<string, array{aliases: array<string, array<mixed>> }>
     */
    private function getAliases(?string $indexName): array
    {
        $endpoint = new Get();
        if (null !== $indexName) {
            $endpoint->setIndex($indexName);
        }
        try {
            $result = $this->client->requestEndpoint($endpoint);
        } catch (ResponseException $e) {
            return [];
        }
        $data = $result->getData();
        if (!\is_array($data)) {
            return [];
        }

        return $data;
    }
}
