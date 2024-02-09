<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\Nested;
use Elastica\Query\Prefix;
use Elastica\Query\Term;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Component\ComponentModal;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\File\MediaLibraryFile;
use EMS\CoreBundle\Core\Component\MediaLibrary\File\MediaLibraryFileFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolder;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolderFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolders;
use EMS\CoreBundle\Core\Component\MediaLibrary\Template\MediaLibraryTemplateFactory;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\MediaLibraryException;
use EMS\CoreBundle\Exception\NotFoundException;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\UserInterface;

class MediaLibraryService
{
    public function __construct(
        private readonly ElasticaService $elasticaService,
        private readonly RevisionService $revisionService,
        private readonly DataService $dataService,
        private readonly JobService $jobService,
        private readonly FileService $fileService,
        private readonly MediaLibraryTemplateFactory $templateFactory,
        private readonly MediaLibraryFileFactory $fileFactory,
        private readonly MediaLibraryFolderFactory $folderFactory
    ) {
    }

    /**
     * @param array{ filename: string, filesize: int, mimetype?: string, sha1: string } $file
     */
    public function createFile(MediaLibraryConfig $config, array $file, ?MediaLibraryFolder $folder = null): bool
    {
        $path = $folder ? $folder->getPath()->getValue().'/' : '/';
        $file['mimetype'] ??= $this->getMimeType($file['sha1']);

        $createdUuid = $this->create($config, [
            $config->fieldPath => $path.$file['filename'],
            $config->fieldFolder => $path,
            $config->fieldFile => \array_filter($file),
        ]);

        return null !== $createdUuid;
    }

    public function createFolder(MediaLibraryConfig $config, string $folderName, ?MediaLibraryFolder $parentFolder = null): ?MediaLibraryFolder
    {
        $path = $parentFolder ? $parentFolder->getPath()->getValue().'/' : '/';

        $createdUuid = $this->create($config, [
            $config->fieldPath => $path.$folderName,
            $config->fieldFolder => $path,
        ]);

        return $createdUuid ? $this->getFolder($config, $createdUuid) : null;
    }

    /**
     * @param string[] $fileIds
     */
    public function deleteFiles(MediaLibraryConfig $config, array $fileIds): bool
    {
        foreach ($this->fileFactory->createFromArray($config, $fileIds) as $mediaFile) {
            $this->deleteDocument($mediaFile);
        }

        return true;
    }

    public function getFile(MediaLibraryConfig $config, string $ouuid): MediaLibraryFile
    {
        return $this->fileFactory->create($config, $ouuid);
    }

    /**
     * @return array{
     *     totalRows?: int,
     *     remaining?: bool,
     *     header?: string,
     *     rowHeader?: string,
     *     rows?: string
     * }
     */
    public function getFiles(MediaLibraryConfig $config, int $from, ?MediaLibraryFolder $folder = null): array
    {
        $path = $folder ? $folder->getPath()->getValue().'/' : '/';

        $findFiles = $this->findFilesByPath($config, $path, $from);
        $template = $this->templateFactory->create($config, \array_filter([
            'folder' => $folder,
            'mediaFiles' => $findFiles['files'],
        ]));

        return \array_filter([
            'totalRows' => $findFiles['total_documents'],
            'remaining' => ($from + $findFiles['total_documents'] < $findFiles['total']),
            'header' => 0 === $from ? $this->renderHeader($config, $folder) : null,
            'rowHeader' => 0 === $from ? $template->block('media_lib_file_row_header') : null,
            'rows' => $template->block('media_lib_file_rows'),
        ]);
    }

    public function getFolder(MediaLibraryConfig $config, string $ouuid): MediaLibraryFolder
    {
        return $this->folderFactory->create($config, $ouuid);
    }

    /**
     * @return array<string, array{ id: string, name: string, path: string, children: array<string, mixed> }>
     */
    public function getFolders(MediaLibraryConfig $config): array
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMustNot((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)));

        $folders = new MediaLibraryFolders($config);
        $scroll = $this->elasticaService->scroll($this->buildSearch($config, $query));

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                $document = Document::fromResult($result);
                $folders->addDocument($document);
            }
        }

        return $folders->getStructure();
    }

    private function getRevision(MediaLibraryDocument $mediaLibraryDocument): Revision
    {
        $document = $mediaLibraryDocument->document;
        $revision = $this->revisionService->getCurrentRevisionForDocument($document);

        if (null === $revision) {
            throw NotFoundException::revisionByOuuid($document);
        }

        return $revision;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function modal(MediaLibraryConfig $config, array $context): ComponentModal
    {
        $componentModal = new ComponentModal($this->templateFactory->create($config), 'media_lib_modal');
        $componentModal->template->context->append($context);

        return $componentModal;
    }

    public function jobFolderDelete(MediaLibraryConfig $config, UserInterface $user, MediaLibraryFolder $folder): Job
    {
        $revision = $this->getRevision($folder);
        if ($revision->isLocked()) {
            throw new MediaLibraryException('media_library.locked');
        }

        $this->revisionService->lock($revision, $user, new \DateTime('+1 hour'));

        $command = \vsprintf('%s --hash=%s --username=%s -- %s', [
            Commands::MEDIA_LIB_FOLDER_DELETE,
            $config->getHash(),
            $user->getUserIdentifier(),
            $folder->id,
        ]);

        return $this->jobService->createCommand($user, $command);
    }

    public function jobFolderRename(MediaLibraryConfig $config, UserInterface $user, MediaLibraryFolder $folder): Job
    {
        $revision = $this->getRevision($folder);
        if ($revision->isLocked()) {
            throw new MediaLibraryException('media_library.locked');
        }

        $this->revisionService->lock($revision, $user, new \DateTime('+1 hour'));

        $command = \vsprintf("%s --hash=%s --username=%s -- %s '%s'", [
            Commands::MEDIA_LIB_FOLDER_RENAME,
            $config->getHash(),
            $user->getUserIdentifier(),
            $folder->id,
            $folder->getName(),
        ]);

        return $this->jobService->createCommand($user, $command);
    }

    /**
     * @param string[] $fileIds
     */
    public function renderHeader(MediaLibraryConfig $config, MediaLibraryFolder|string|null $folder, array $fileIds = []): string
    {
        $mediaFolder = \is_string($folder) ? $this->getFolder($config, $folder) : $folder;
        $mediaFiles = $this->fileFactory->createFromArray($config, $fileIds);

        $template = $this->templateFactory->create($config, \array_filter([
            'mediaFolder' => $mediaFolder,
            'mediaFile' => 1 === \count($mediaFiles) ? $mediaFiles[0] : null,
            'mediaFiles' => \count($mediaFiles) > 1 ? $mediaFiles : null,
        ]));

        return $template->block('media_lib_header');
    }

    public function renderFileRow(MediaLibraryConfig $config, MediaLibraryFile $mediaLibraryFile): string
    {
        return $this->templateFactory
            ->create($config, ['mediaFile' => $mediaLibraryFile])
            ->block('media_lib_file_row');
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

    public function updateDocument(MediaLibraryDocument $mediaDocument, ?string $username = null): void
    {
        $document = $mediaDocument->document;

        $this->revisionService->updateRawDataByEmsLink(
            emsLink: $document->getEmsLink(),
            rawData: $document->getSource(true),
            username: $username
        );
    }

    public function deleteDocument(MediaLibraryDocument $mediaDocument, ?string $username = null): void
    {
        $document = $mediaDocument->document;
        $this->dataService->delete($document->getContentType(), $document->getOuuid(), $username);
    }

    public function refresh(MediaLibraryConfig $config): void
    {
        $this->elasticaService->refresh($config->contentType->giveEnvironment()->getAlias());
    }

    private function getMimeType(string $fileHash): string
    {
        $tempFile = $this->fileService->temporaryFilename($fileHash);
        \file_put_contents($tempFile, $this->fileService->getResource($fileHash));

        $type = (new File($tempFile))->getMimeType();

        return $type ?: 'application/bin';
    }

    private function buildSearch(MediaLibraryConfig $config, BoolQuery $query): Search
    {
        if ($config->searchQuery) {
            $query->addMust($config->searchQuery);
        }

        $search = new Search([$config->contentType->giveEnvironment()->getAlias()], $query);
        $search->setContentTypes([$config->contentType->getName()]);

        if ($config->fieldPathOrder) {
            $search->setSort([$config->fieldPathOrder => ['order' => 'asc']]);
        }

        return $search;
    }

    public function countByPath(MediaLibraryConfig $config, string $path): int
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMust(new Prefix([$config->fieldFolder => $path]));
        $search = $this->buildSearch($config, $query);
        $search->setSize(0);

        return $this->elasticaService->count($search);
    }

    /**
     * @return \Generator<MediaLibraryDocument>
     */
    public function findByPath(MediaLibraryConfig $config, string $path): \Generator
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMust(new Prefix([$config->fieldFolder => $path]));

        $scroll = $this->elasticaService->scroll($this->buildSearch($config, $query));

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                yield new MediaLibraryDocument(Document::fromResult($result), $config);
            }
        }
    }

    /**
     * @return array{ files: MediaLibraryFile[], total: int, total_documents: int}
     */
    private function findFilesByPath(MediaLibraryConfig $config, string $path, int $from): array
    {
        $query = $this->elasticaService->getBoolQuery();
        $query
            ->addMust((new Nested())->setPath($config->fieldFile)->setQuery(new Exists($config->fieldFile)))
            ->addMust((new Term())->setTerm($config->fieldFolder, $path));

        $search = $this->buildSearch($config, $query);
        $search->setFrom($from);
        $search->setSize($config->searchSize);

        $result = Response::fromResultSet($this->elasticaService->search($search));

        return [
            'files' => $this->fileFactory->createFromDocumentCollection($config, $result->getDocumentCollection()),
            'total' => $result->getTotal(),
            'total_documents' => $result->getTotalDocuments(),
        ];
    }
}
