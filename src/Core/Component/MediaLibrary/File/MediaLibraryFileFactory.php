<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\File;

use EMS\CommonBundle\Elasticsearch\Document\DocumentCollectionInterface;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MediaLibraryFileFactory
{
    public function __construct(
        private readonly ElasticaService $elasticaService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(MediaLibraryConfig $config, string $fileId): MediaLibraryFile
    {
        $index = $config->contentType->giveEnvironment()->getAlias();
        $document = $this->elasticaService->getDocument($index, $config->contentType->getName(), $fileId);

        return $this->createFromDocument($config, $document);
    }

    public function createFromDocument(MediaLibraryConfig $config, DocumentInterface $document): MediaLibraryFile
    {
        return new MediaLibraryFile($document, $config, $this->urlGenerator);
    }

    /**
     * @return MediaLibraryFile[]
     */
    public function createFromDocumentCollection(MediaLibraryConfig $config, DocumentCollectionInterface $documentCollection): array
    {
        $mediaLibraryFiles = [];

        foreach ($documentCollection as $document) {
            $mediaLibraryFiles[] = $this->createFromDocument($config, $document);
        }

        return $mediaLibraryFiles;
    }
}
