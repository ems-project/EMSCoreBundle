<?php

namespace EMS\CoreBundle\Service;

use Elastica\Client;
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
            try {
                $this->client->getIndex($index['name'])->delete();
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

    public function indexRevision(Revision $revision, ?Environment $environment = null): void
    {
        $contentType = $revision->getContentType();
        if ($contentType === null) {
            throw new \RuntimeException('Unexpected null content type');
        }
        if ($environment === null) {
            $environment = $contentType->getEnvironment();
        }
        if ($environment === null) {
            throw new \RuntimeException('Unexpected null environment');
        }
        $index = $this->contentTypeService->getIndex($contentType, $environment);
        $path = $this->mapping->getTypePath($contentType);
        $this->client->getIndex($index)->getType($path)->createDocument($revision->getOuuid(), $revision->getRawData());
    }
}
