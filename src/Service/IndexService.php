<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use EMS\CommonBundle\Elasticsearch\Client;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\Response;

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
        } catch (ClientResponseException $e) {
            if (Response::HTTP_NOT_FOUND === $e->getResponse()->getStatusCode()) {
                throw throw NotFoundException::index($indexName);
            }
            throw $e;
        }
    }

    public function indexRevision(Revision $revision, ?Environment $environment = null): bool
    {
        $contentType = $revision->getContentType();
        if (null === $contentType) {
            throw new \RuntimeException('Unexpected null content type');
        }
        if (null === $environment) {
            $environment = $contentType->giveEnvironment();
        }
        $objectArray = $revision->getRawData($environment);

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
        $source[Mapping::PUBLISHED_DATETIME_FIELD] = new \DateTime()->format(\DateTimeInterface::ATOM);
        $source[EMSSource::FIELD_CONTENT_TYPE] = $contentTypeName;

        $params = [
            'index' => $index,
            'body' => $source,
        ];
        if (null !== $ouuid) {
            $params['id'] = $ouuid;
        }

        $result = $this->client->resolveResponse($this->client->index($params))->getData();

        $ouuid = null;
        if (\is_array($result) && (int) ($result['_shards']['successful'] ?? 0) > 0) {
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
        return $this->client->getIndex($name)->exists();
    }

    /**
     * @return string[]
     */
    public function getIndexesByAlias(string $alias): array
    {
        return \array_keys($this->getAliases($alias));
    }

    /**
     * @return string[]
     */
    public function getAliasesByIndex(string $indexName): array
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

        return $this->client->resolveResponse(
            $this->client->indices()->updateAliases(['body' => ['actions' => $actions]])
        )->isOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function getAliases(string $indexName): array
    {
        try {
            return $this->client->resolveResponse(
                $this->client->indices()->getAlias(['index' => $indexName])
            )->getData();
        } catch (ClientResponseException $e) {
            if (Response::HTTP_NOT_FOUND === $e->getResponse()->getStatusCode()) {
                return [];
            }
            throw $e;
        }
    }
}
