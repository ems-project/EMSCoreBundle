<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\Nested;
use Elastica\Query\Term;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MediaLibraryService
{
    private const MAX_FOLDERS = 5000;

    public function __construct(
        private readonly ElasticaService $elasticaService,
        private readonly RevisionService $revisionService,
        private readonly DataService $dataService,
        private readonly FileService $fileService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MediaLibraryTemplateFactory $templateFactory
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
            $config->fieldFolder => $path,
            $config->fieldFile => \array_filter(\array_merge($file, [
                'sha1' => $fileHash,
            ])),
        ]);
    }

    public function createFolder(MediaLibraryConfig $config, string $folderName, string $path): bool
    {
        return $this->create($config, [
            $config->fieldPath => $path.$folderName,
            $config->fieldFolder => $path,
        ]);
    }

    /**
     * @return array{
     *     totalRows?: int,
     *     remaining?: bool,
     *     rowHeader?: string,
     *     rows?: string[]
     * }
     */
    public function getFiles(MediaLibraryConfig $config, string $path, int $from): array
    {
        $searchQuery = $this->elasticaService->getBoolQuery();
        $searchQuery->addMust((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)));

        if ($path) {
            $searchQuery->addMust((new Term())->setTerm($config->fieldFolder, $path));
        }

        $template = $this->templateFactory->create($config);
        $search = $this->search($config, $searchQuery, $config->searchSize, $from);

        $rows = [];
        foreach ($search->getDocuments() as $document) {
            $mediaLibraryFile = new MediaLibraryFile($config, $document);
            $rows[] = $template->block(MediaLibraryTemplate::BLOCK_FILE_ROW, [
                'media' => $mediaLibraryFile,
                'url' => $this->urlGenerator->generate('ems.file.view', [
                    'sha1' => $mediaLibraryFile->file['sha1'],
                    'filename' => $mediaLibraryFile->file['filename'],
                ]),
            ]);
        }

        return \array_filter([
            'totalRows' => $search->getTotalDocuments(),
            'remaining' => ($from + $search->getTotalDocuments() < $search->getTotal()),
            'rowHeader' => 0 == $from ? $template->block(MediaLibraryTemplate::BLOCK_FILE_ROW_HEADER) : null,
            'rows' => $rows,
        ]);
    }

    /**
     * @return array<int, array{ name: string }>
     */
    public function getFolders(MediaLibraryConfig $config): array
    {
        $searchQuery = $this->elasticaService->getBoolQuery();
        $searchQuery->addMustNot((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)));

        $docs = $this->search($config, $searchQuery, self::MAX_FOLDERS)->getDocuments();

        $folders = new MediaLibraryFolders();

        foreach ($docs as $document) {
            $folderPath = $document->getValue($config->fieldPath);
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

    private function getMimeType(string $fileHash): string
    {
        $tempFile = $this->fileService->temporaryFilename($fileHash);
        \file_put_contents($tempFile, $this->fileService->getResource($fileHash));

        $type = (new File($tempFile))->getMimeType();

        return $type ?: 'application/bin';
    }

    private function search(MediaLibraryConfig $config, BoolQuery $query, int $size, int $from = 0): Response
    {
        if ($config->searchQuery) {
            $query->addMust($config->searchQuery);
        }

        $search = new Search([$config->contentType->giveEnvironment()->getAlias()], $query);
        $search->setContentTypes([$config->contentType->getName()]);
        $search->setFrom($from);
        $search->setSize($size);

        if ($config->fieldPathOrder) {
            $search->setSort([$config->fieldPathOrder => ['order' => 'asc']]);
        }

        return Response::fromResultSet($this->elasticaService->search($search));
    }
}
