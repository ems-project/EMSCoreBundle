<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Folder;

use Elastica\Query\Term;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryConfig;

class MediaLibraryFolderFactory
{
    public function __construct(
        private readonly ElasticaService $elasticaService,
        private readonly MediaLibraryConfig $config)
    {
    }

    public function create(string $ouuid): MediaLibraryFolder
    {
        $index = $this->config->contentType->giveEnvironment()->getAlias();
        $document = $this->elasticaService->getDocument($index, $this->config->contentType->getName(), $ouuid);

        return $this->createFromDocument($document);
    }

    private function createFromDocument(DocumentInterface $document): MediaLibraryFolder
    {
        $folder = MediaLibraryFolder::fromDocument($this->config, $document);

        if ($parentPath = $folder->getParentPath()) {
            $parentDocument = $this->searchParent($parentPath);
            $folder->setParent($this->createFromDocument($parentDocument));
        }

        return $folder;
    }

    private function searchParent(string $path): DocumentInterface
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMust((new Term())->setTerm($this->config->fieldPath, $path));

        $search = new Search([$this->config->contentType->giveEnvironment()->getAlias()], $query);
        $search->setContentTypes([$this->config->contentType->getName()]);

        return $this->elasticaService->singleSearch($search);
    }
}
