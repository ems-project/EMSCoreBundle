<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\Nested;
use Elastica\Query\Prefix;
use Elastica\Query\Term;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\QueryStringEscaper;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Component\ComponentModal;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfigFactory;
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
use EMS\Helpers\Standard\Json;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\UserInterface;

use function Symfony\Component\String\u;

class MediaLibraryService
{
    private ?MediaLibraryConfig $config = null;

    public function __construct(
        private readonly ElasticaService $elasticaService,
        private readonly RevisionService $revisionService,
        private readonly DataService $dataService,
        private readonly JobService $jobService,
        private readonly FileService $fileService,
        private readonly MediaLibraryConfigFactory $configFactory,
        private readonly MediaLibraryTemplateFactory $templateFactory,
        private readonly MediaLibraryFileFactory $fileFactory,
        private readonly MediaLibraryFolderFactory $folderFactory
    ) {
    }

    public function count(string $path, string $excludeId = null): int
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMust((new Term())->setTerm($this->getConfig()->fieldPath, $path));

        if ($excludeId) {
            $query->addMustNot((new Term())->setTerm('_id', $excludeId));
        }

        $search = $this->buildSearch($query, false);

        return $this->elasticaService->count($search);
    }

    public function countChildren(string $folder): int
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMust(new Prefix([$this->getConfig()->fieldFolder => $folder]));
        $search = $this->buildSearch($query);
        $search->setSize(0);

        return $this->elasticaService->count($search);
    }

    /**
     * @param array{ filename: string, filesize: int, mimetype?: string, sha1: string } $file
     */
    public function createFile(array $file, MediaLibraryFolder $folder = null): bool
    {
        $path = $folder ? $folder->getPath()->getValue().'/' : '/';
        $file['mimetype'] ??= $this->getMimeType($file['sha1']);

        $createdUuid = $this->create([
            $this->getConfig()->fieldPath => $path.$file['filename'],
            $this->getConfig()->fieldFolder => $path,
            $this->getConfig()->fieldFile => \array_filter($file),
        ]);

        return null !== $createdUuid;
    }

    public function createFolder(MediaLibraryDocumentDTO $documentDTO): ?MediaLibraryFolder
    {
        $createdUuid = $this->create([
            $this->getConfig()->fieldPath => $documentDTO->getPath(),
            $this->getConfig()->fieldFolder => $documentDTO->getFolder(),
        ]);

        return $createdUuid ? $this->getFolder($createdUuid) : null;
    }

    public function deleteDocument(MediaLibraryDocument $mediaDocument, string $username = null): void
    {
        $document = $mediaDocument->document;
        $this->dataService->delete($document->getContentType(), $document->getOuuid(), $username);
    }

    /**
     * @return \Generator<MediaLibraryDocument>
     */
    public function findChildrenByPath(string $path): \Generator
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMust(new Prefix([$this->getConfig()->fieldFolder => $path]));

        $scroll = $this->elasticaService->scroll($this->buildSearch($query));

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                yield new MediaLibraryDocument(Document::fromResult($result), $this->getConfig());
            }
        }
    }

    public function getFile(string $ouuid): MediaLibraryFile
    {
        return $this->fileFactory->create($this->getConfig(), $ouuid);
    }

    public function getFolder(string $ouuid): MediaLibraryFolder
    {
        return $this->folderFactory->create($this->getConfig(), $ouuid);
    }

    public function getFolders(): MediaLibraryFolders
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMustNot((new Nested())->setPath($this->getConfig()->fieldFile)->setQuery(new Exists($this->getConfig()->fieldFile)));

        $folders = new MediaLibraryFolders($this->getConfig());
        $scroll = $this->elasticaService->scroll($this->buildSearch($query));

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                $document = Document::fromResult($result);
                $folders->addDocument($document);
            }
        }

        return $folders;
    }

    public function jobFolderDelete(UserInterface $user, MediaLibraryFolder $folder): Job
    {
        $revision = $this->getRevision($folder);
        if ($revision->isLocked()) {
            throw new MediaLibraryException('media_library.locked');
        }

        $this->revisionService->lock($revision, $user, new \DateTime('+1 hour'));

        $command = \vsprintf('%s --hash=%s --username=%s -- %s', [
            Commands::MEDIA_LIB_FOLDER_DELETE,
            $this->getConfig()->getHash(),
            $user->getUserIdentifier(),
            $folder->id,
        ]);

        return $this->jobService->createCommand($user, $command);
    }

    public function jobFolderRename(UserInterface $user, MediaLibraryFolder $folder): Job
    {
        $revision = $this->getRevision($folder);
        if ($revision->isLocked()) {
            throw new MediaLibraryException('media_library.locked');
        }

        $this->revisionService->lock($revision, $user, new \DateTime('+1 hour'));

        $command = \vsprintf("%s --hash=%s --username=%s -- %s '%s'", [
            Commands::MEDIA_LIB_FOLDER_RENAME,
            $this->getConfig()->getHash(),
            $user->getUserIdentifier(),
            $folder->id,
            $folder->getName(),
        ]);

        return $this->jobService->createCommand($user, $command);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function modal(array $context): ComponentModal
    {
        $componentModal = new ComponentModal($this->templateFactory->create($this->getConfig()), 'media_lib_modal');
        $componentModal->template->context->append($context);

        return $componentModal;
    }

    public function refresh(): void
    {
        $this->elasticaService->refresh($this->getConfig()->contentType->giveEnvironment()->getAlias());
    }

    public function renderFileRow(MediaLibraryFile $mediaLibraryFile): string
    {
        return $this->templateFactory
            ->create($this->getConfig(), ['mediaFile' => $mediaLibraryFile])
            ->block('media_lib_file_row');
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
    public function renderFiles(int $from, MediaLibraryFolder $folder = null, string $searchValue = null): array
    {
        $path = $folder ? $folder->getPath()->getValue().'/' : '/';

        $findFiles = $this->findFilesByPath($path, $from, $searchValue);
        $template = $this->templateFactory->create($this->getConfig(), \array_filter([
            'folder' => $folder,
            'mediaFiles' => $findFiles['files'],
        ]));

        return \array_filter([
            'totalRows' => $findFiles['total_documents'],
            'remaining' => ($from + $findFiles['total_documents'] < $findFiles['total']),
            'header' => 0 === $from ? $this->renderHeader(folder: $folder, searchValue: $searchValue) : null,
            'rowHeader' => 0 === $from ? $template->block('media_lib_file_header_row') : null,
            'rows' => $template->block('media_lib_file_rows'),
        ]);
    }

    public function renderFolders(): string
    {
        $folders = $this->getFolders();
        $template = $this->templateFactory->create($this->getConfig(), ['structure' => $folders->getStructure()]);

        return $template->block('media_lib_folder_rows');
    }

    public function renderHeader(MediaLibraryFolder|string $folder = null, MediaLibraryFile|string $file = null, int $selectionFiles = 0, string $searchValue = null): string
    {
        $mediaFolder = \is_string($folder) ? $this->getFolder($folder) : $folder;
        $mediaFile = \is_string($file) ? $this->getFile($file) : $file;

        if ($mediaFile) {
            $selectionFiles = 1;
        }

        $template = $this->templateFactory->create($this->getConfig(), \array_filter([
            'mediaFolder' => $mediaFolder,
            'mediaFile' => $mediaFile,
            'selectionFiles' => $selectionFiles,
            'searchValue' => $searchValue,
        ], static fn ($v) => null !== $v));

        return $template->block('media_lib_header');
    }

    public function setConfig(MediaLibraryConfig $config): void
    {
        $this->config = $config;
    }

    public function updateDocument(MediaLibraryDocument $mediaDocument, string $username = null): void
    {
        $document = $mediaDocument->document;

        $this->revisionService->updateRawDataByEmsLink(
            emsLink: $document->getEmsLink(),
            rawData: $document->getSource(true),
            username: $username
        );
    }

    private function buildSearch(BoolQuery $query, bool $includeSearchQuery = true): Search
    {
        if ($includeSearchQuery && $this->getConfig()->searchQuery) {
            $query->addMust($this->getConfig()->searchQuery);
        }

        $search = new Search([$this->getConfig()->contentType->giveEnvironment()->getAlias()], $query);
        $search->setContentTypes([$this->getConfig()->contentType->getName()]);

        if ($this->getConfig()->fieldPathOrder) {
            $search->setSort([$this->getConfig()->fieldPathOrder => ['order' => 'asc']]);
        }

        return $search;
    }

    /**
     * @param array<mixed> $rawData
     */
    private function create(array $rawData): ?string
    {
        $uuid = Uuid::uuid4();
        $rawData = \array_merge_recursive($this->getConfig()->defaultValue, $rawData);
        $revision = $this->revisionService->create($this->getConfig()->contentType, $uuid, $rawData);

        $form = $this->revisionService->createRevisionForm($revision);
        $this->dataService->finalizeDraft($revision, $form);

        $this->elasticaService->refresh($this->getConfig()->contentType->giveEnvironment()->getAlias());

        return 0 === $form->getErrors(true)->count() ? $uuid->toString() : null;
    }

    /**
     * @return array{ files: MediaLibraryFile[], total: int, total_documents: int}
     */
    private function findFilesByPath(string $path, int $from, string $searchValue = null): array
    {
        $query = $this->elasticaService->getBoolQuery();
        $query
            ->addMust((new Nested())->setPath($this->getConfig()->fieldFile)->setQuery(new Exists($this->getConfig()->fieldFile)))
            ->addMust((new Term())->setTerm($this->getConfig()->fieldFolder, $path));

        if ($searchValue) {
            $jsonSearchFileQuery = Json::encode($this->getConfig()->searchFileQuery);

            $searchFileQuery = Json::decode(u($jsonSearchFileQuery)
                ->replace('%query%', Json::escape(QueryStringEscaper::escape($searchValue)))
                ->toString());

            if (!isset($searchFileQuery['bool'])) {
                throw new \RuntimeException('Search file query search should be a bool query');
            }

            $query->addMust((new BoolQuery())->setParams($searchFileQuery['bool']));
        }

        $search = $this->buildSearch($query);
        $search->setFrom($from);
        $search->setSize($this->getConfig()->searchSize);

        $result = Response::fromResultSet($this->elasticaService->search($search));

        return [
            'files' => $this->fileFactory->createFromDocumentCollection($this->getConfig(), $result->getDocumentCollection()),
            'total' => $result->getTotal(),
            'total_documents' => $result->getTotalDocuments(),
        ];
    }

    private function getConfig(): MediaLibraryConfig
    {
        if (null === $this->config) {
            /** @var MediaLibraryConfig $config */
            $config = $this->configFactory->createFromRequest();
            $this->config = $config;
        }

        return $this->config;
    }

    private function getMimeType(string $fileHash): string
    {
        $tempFile = $this->fileService->temporaryFilename($fileHash);
        \file_put_contents($tempFile, $this->fileService->getResource($fileHash));

        $type = (new File($tempFile))->getMimeType();

        return $type ?: 'application/bin';
    }

    private function getRevision(MediaLibraryDocument $mediaLibraryDocument): Revision
    {
        $document = $mediaLibraryDocument->document;
        $revision = $this->revisionService->getCurrentRevisionForDocument($document);

        if (null === $revision) {
            throw NotFoundException::revisionForDocument($document);
        }

        return $revision;
    }
}
