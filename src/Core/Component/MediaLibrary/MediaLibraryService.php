<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Elastica\Document;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\Nested;
use Elastica\Query\Term;
use Elastica\ResultSet;
use EMS\CommonBundle\Common\Converter;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class MediaLibraryService
{
    public function __construct(
        private readonly ElasticaService $elasticaService,
        private readonly RevisionService $revisionService,
        private readonly DataService $dataService,
        private readonly FileService $fileService,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * @param array{filename: string, filesize: string, mimetype: string} $file
     */
    public function createFile(MediaLibraryConfig $config, string $fileHash, array $file, string $path): bool
    {
        $file['mimetype'] = ('' === $file['mimetype'] ? $this->getMimeType($fileHash) : $file['mimetype']);

        return $this->create($config, [
            $config->fieldPath => $path.$file['filename'],
            $config->fieldLocation => $path,
            $config->fieldFile => \array_filter(\array_merge($file, [
                'sha1' => $fileHash,
            ])),
        ]);
    }

    public function createFolder(MediaLibraryConfig $config, string $folderName, string $path): bool
    {
        return $this->create($config, [
            $config->fieldPath => $path.$folderName,
            $config->fieldLocation => $path,
        ]);
    }

    /**
     * @return array<int, array{
     *      path: string,
     *      file?: array{name: string, size: string, type: string, hash: string }
     * }>
     */
    public function getFiles(MediaLibraryConfig $config, string $path): array
    {
        $searchQuery = $this->elasticaService->getBoolQuery();
        $searchQuery->addMust((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)));

        if ($path) {
            $searchQuery->addMust((new Term())->setTerm($config->fieldLocation, $path));
        }

        $docs = $this->search($config, $searchQuery)->getDocuments();

        return \array_map(fn (Document $doc) => $this->getFile($config, $doc)->toArray(), $docs);
    }

    /**
     * @return array<int, array{ name: string }>
     */
    public function getFolders(MediaLibraryConfig $config): array
    {
        $searchQuery = $this->elasticaService->getBoolQuery();
        $searchQuery->addMustNot((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)));

        $docs = $this->search($config, $searchQuery)->getDocuments();

        $folders = new MediaLibraryFolders();

        foreach ($docs as $document) {
            $folderPath = $document->get($config->fieldPath);
            $currentPath = \array_filter(\explode('/', $folderPath));
            $folderName = \basename($folderPath);
            $folders->add($currentPath, $folderName, $folderPath);
        }

        return $folders->toArray();
    }

    /**
     * @param array<mixed> $rawData
     */
    private function create(MediaLibraryConfig $config, array $rawData): bool
    {
        $rawData = \array_merge_recursive($config->defaultValue, $rawData);
        $revision = $this->revisionService->create($config->contentType, Uuid::uuid4(), $rawData);

        $form = $this->revisionService->createRevisionForm($revision);
        $this->dataService->finalizeDraft($revision, $form);

        $this->elasticaService->refresh($config->contentType->giveEnvironment()->getAlias());

        return 0 === $form->getErrors(true)->count();
    }

    private function getFile(MediaLibraryConfig $config, Document $document): MediaLibraryFile
    {
        $file = MediaLibraryFile::createFromDocument($config, $document);
        $file->size = Converter::formatBytes((int) $file->size);

        if ($file->type) {
            $file->type = $this->translator->trans($file->type, [], EMSCoreBundle::TRANS_MIMETYPES);
        }

        return $file;
    }

    private function getMimeType(string $fileHash): string
    {
        $tempFile = $this->fileService->temporaryFilename($fileHash);
        \file_put_contents($tempFile, $this->fileService->getResource($fileHash));

        $type = (new File($tempFile))->getMimeType();

        return $type ?: 'application/bin';
    }

    private function search(MediaLibraryConfig $config, BoolQuery $query): ResultSet
    {
        if ($config->searchQuery) {
            $query->addMust($config->searchQuery);
        }

        $search = new Search([$config->contentType->giveEnvironment()->getAlias()], $query);
        $search->setContentTypes([$config->contentType->getName()]);
        $search->setFrom(0);
        $search->setSize(5000);

        if ($config->fieldPathOrder) {
            $search->setSort([$config->fieldPathOrder => ['order' => 'asc']]);
        }

        return $this->elasticaService->search($search);
    }
}
