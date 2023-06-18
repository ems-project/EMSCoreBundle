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
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolder;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolderFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolderStructure;
use EMS\CoreBundle\Core\Component\MediaLibrary\Request\MediaLibraryRequest;
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

    public function createFile(MediaLibraryConfig $config, MediaLibraryRequest $request): bool
    {
        $path = $this->getFolderPath($config, $request);

        $file = $request->getContentJson()['file'];
        $file['mimetype'] = ('' === $file['mimetype'] ? $this->getMimeType($file['sha1']) : $file['mimetype']);

        $createdUuid = $this->create($config, [
            $config->fieldPath => $path.$file['filename'],
            $config->fieldFolder => $path,
            $config->fieldFile => \array_filter($file),
        ]);

        return null !== $createdUuid;
    }

    public function createFolder(MediaLibraryConfig $config, MediaLibraryRequest $request, string $folderName): ?MediaLibraryFolder
    {
        $path = $this->getFolderPath($config, $request);

        $createdUuid = $this->create($config, [
            $config->fieldPath => $path.$folderName,
            $config->fieldFolder => $path,
        ]);

        return $createdUuid ? $this->getFolder($config, $createdUuid) : null;
    }

    /**
     * @return array{
     *     totalRows?: int,
     *     remaining?: bool,
     *     rowHeader?: string,
     *     rows?: string[]
     * }
     */
    public function getFiles(MediaLibraryConfig $config, MediaLibraryRequest $request): array
    {
        $folder = $request->folderId ? $this->getFolder($config, $request->folderId) : null;
        $path = $this->getFolderPath($config, $request);

        $searchQuery = $this->elasticaService->getBoolQuery();
        $searchQuery
            ->addMust((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)))
            ->addMust((new Term())->setTerm($config->fieldFolder, $path));

        $template = $this->templateFactory->create($config);
        $search = $this->search($config, $searchQuery, $config->searchSize, $request->from);

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
            'remaining' => ($request->from + $search->getTotalDocuments() < $search->getTotal()),
            'header' => 0 === $request->from ? $template->renderHeader(['folder' => $folder]) : null,
            'rowHeader' => 0 === $request->from ? $template->block(MediaLibraryTemplate::BLOCK_FILE_ROW_HEADER) : null,
            'rows' => $rows,
        ]);
    }

    public function getFolder(MediaLibraryConfig $config, string $ouuid): MediaLibraryFolder
    {
        return (new MediaLibraryFolderFactory($this->elasticaService, $config))->create($ouuid);
    }

    /**
     * @return array<string, array{ id: string, name: string, path: string, children: array<string, mixed> }>
     */
    public function getFolders(MediaLibraryConfig $config): array
    {
        $searchQuery = $this->elasticaService->getBoolQuery();
        $searchQuery->addMustNot((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)));

        $documents = $this->search($config, $searchQuery, self::MAX_FOLDERS)->getDocuments();

        return MediaLibraryFolderStructure::create($config, $documents)->toArray();
    }

    /**
     * @param array<mixed> $rawData
     */
    private function create(MediaLibraryConfig $config, array $rawData): ?string
    {
        $uuid = Uuid::uuid4();
        $rawData = \array_merge_recursive($config->defaultValue, $rawData);
        $revision = $this->revisionService->create($config->contentType, $uuid, $rawData);

        $form = $this->revisionService->createRevisionForm($revision);
        $this->dataService->finalizeDraft($revision, $form);

        $this->elasticaService->refresh($config->contentType->giveEnvironment()->getAlias());

        return 0 === $form->getErrors(true)->count() ? $uuid->toString() : null;
    }

    private function getFolderPath(MediaLibraryConfig $config, MediaLibraryRequest $request): string
    {
        return $request->folderId ? $this->getFolder($config, $request->folderId)->path.'/' : '/';
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
