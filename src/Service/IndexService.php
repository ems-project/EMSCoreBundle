<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Elastica\Exception\ResponseException;
use Elasticsearch\Endpoints\Index;
use Elasticsearch\Endpoints\Indices\Exists;
use Elasticsearch\Endpoints\Indices\GetAlias;
use Elasticsearch\Endpoints\Indices\UpdateAliases;
use EMS\CommonBundle\Elasticsearch\Client;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\NotFoundException;

final readonly class IndexService
{
    public function __construct(
        private AliasService $aliasService,
        private Client $client,
        private ContentTypeService $contentTypeService,
    ) {
    }

    public function deleteOrphanIndexes(): void
    {
        $this->aliasService->build();
        foreach ($this->aliasService->getOrphanIndexes() as $index) {
            $this->deleteIndex($index->name);
        }
    }

    public function deleteIndexes(string ...$indexes): void
    {
        foreach ($indexes as $index) {
            $this->deleteIndex($index);
        }
    }

    public function deleteIndex(string $indexName): void
    {
        try {
            $index = $this->client->getIndex($indexName);
            $countAliases = \count($index->getAliases());

            if ($countAliases > 0) {
                throw new \RuntimeException(\sprintf('The index "%s" can not be deleted because is referenced by %s aliases', $indexName, $countAliases));
            }

            $index->delete();
        } catch (ResponseException $e) {
            match ($e->getResponse()->getStatus()) {
                404 => throw NotFoundException::index($indexName),
                default => throw $e,
            };
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

        $ouuid = $this->indexDocument($this->contentTypeService->getIndex($contentType, $environment), $contentType->getName(), $revision->getOuuid(), $objectArray);
        if (null !== $ouuid && !$revision->hasOuuid()) {
            $revision->setOuuid($ouuid);
        }

        return null !== $ouuid;
    }

    /**
     * @param array<string, mixed> $source
     */
    public function indexDocument(string $index, string $contentTypeName, ?string $ouuid, array $source): ?string
    {
        $source[Mapping::PUBLISHED_DATETIME_FIELD] = (new \DateTime())->format(\DateTimeInterface::ATOM);
        $source[EMSSource::FIELD_CONTENT_TYPE] = $contentTypeName;
        $endpoint = new Index();
        $endpoint->setIndex($index);
        $endpoint->setBody($source);
        if (null !== $ouuid) {
            $endpoint->setID($ouuid);
        }
        $result = $this->client->requestEndpoint($endpoint)->getData();

        $ouuid = null;
        if (\is_array($result) && \intval($result['_shards']['successful'] ?? 0) > 0) {
            $ouuid = $result['_id'];
        }

        return $ouuid;
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
        $this->client->deleteIds([$revision->getOuuid()], $index);
    }

    public function hasIndex(string $name): bool
    {
        $endpoint = new Exists();
        $endpoint->setIndex($name);

        return $this->client->requestEndpoint($endpoint)->isOk();
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
        foreach ($this->getAliases($indexName) as $indexInfo) {
            foreach ($indexInfo['aliases'] ?? [] as $alias => $aliasInfo) {
                if (!\is_string($alias)) {
                    throw new \RuntimeException('Unexpected non string alias name');
                }
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }

    /**
     * @param string[] $indexes
     * @param string[] $indexesToRemove
     */
    public function addIndexesToAlias(string $alias, array $indexes, array $indexesToRemove = []): bool
    {
        $endpoint = new UpdateAliases();
        $actions = [];
        foreach ($indexes as $index) {
            $actions[] = [
                'add' => [
                    'index' => $index,
                    'alias' => $alias,
                ],
            ];
        }
        foreach ($indexesToRemove as $index) {
            $actions[] = [
                'remove' => [
                    'index' => $index,
                    'alias' => $alias,
                ],
            ];
        }
        $endpoint->setBody([
            'actions' => $actions,
        ]);
        $result = $this->client->requestEndpoint($endpoint);

        return $result->isOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function getAliases(?string $indexName): array
    {
        $endpoint = new GetAlias();
        if (null !== $indexName) {
            $endpoint->setIndex($indexName);
        }
        try {
            $result = $this->client->requestEndpoint($endpoint);
        } catch (ResponseException) {
            return [];
        }
        $data = $result->getData();
        if (!\is_array($data)) {
            return [];
        }

        return $data;
    }
}
