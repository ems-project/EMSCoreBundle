<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Folder;

use Elastica\Query\Exists;
use Elastica\Query\Nested;
use Elastica\Query\Term;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryPath;
use Ramsey\Uuid\Uuid;

class MediaLibraryFolderFactory
{
    public function __construct(private readonly ElasticaService $elasticaService)
    {
    }

    public function create(MediaLibraryConfig $config, ?MediaLibraryFolder $parentFolder): MediaLibraryFolder
    {
        $uuid = Uuid::uuid4();
        $rawData = \array_merge_recursive($config->defaultValue, [
            $config->fieldFolder => $parentFolder?->getPath()->getValue().'/',
        ]);

        $document = Document::fromData($config->contentType, $uuid->toString(), $rawData);

        return new MediaLibraryFolder($document, $config);
    }

    public function createFromOuuid(MediaLibraryConfig $config, string $ouuid): MediaLibraryFolder
    {
        $index = $config->contentType->giveEnvironment()->getAlias();
        $document = $this->elasticaService->getDocument($index, $config->contentType->getName(), $ouuid);

        return $this->createFromDocument($config, $document);
    }

    private function createFromDocument(MediaLibraryConfig $config, DocumentInterface $document): MediaLibraryFolder
    {
        $folder = new MediaLibraryFolder($document, $config);

        if ($parentPath = $folder->getPath()->parent()) {
            $parentDocument = $this->searchParent($config, $parentPath);
            $folder->setParent($this->createFromDocument($config, $parentDocument));
        }

        return $folder;
    }

    private function searchParent(MediaLibraryConfig $config, MediaLibraryPath $path): DocumentInterface
    {
        $query = $this->elasticaService->getBoolQuery();
        $query
            ->addMust((new Term())->setTerm($config->fieldPath, $path->getValue()))
            ->addMustNot((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)));

        $search = new Search([$config->contentType->giveEnvironment()->getAlias()], $query);
        $search->setContentTypes([$config->contentType->getName()]);

        return $this->elasticaService->singleSearch($search);
    }
}
