<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Folder;

use Elastica\Query\Term;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryPath;

class MediaLibraryFolderFactory
{
    public function __construct(private readonly ElasticaService $elasticaService)
    {
    }

    public function create(MediaLibraryConfig $config, string $ouuid): MediaLibraryFolder
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
        $query->addMust((new Term())->setTerm($config->fieldPath, $path->getValue()));

        $search = new Search([$config->contentType->giveEnvironment()->getAlias()], $query);
        $search->setContentTypes([$config->contentType->getName()]);

        return $this->elasticaService->singleSearch($search);
    }
}
