<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Elastica\Aggregation\Filter;
use Elastica\Aggregation\Nested as NestedAgg;
use Elastica\Aggregation\Sum;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\Nested as NestedQuery;
use Elastica\Query\Prefix;
use Elastica\Query\Term;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\QueryStringEscaper;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Helper\EmsFields;
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
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use Ramsey\Uuid\Uuid;
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
        private readonly MediaLibraryConfigFactory $configFactory,
        private readonly MediaLibraryTemplateFactory $templateFactory,
        private readonly MediaLibraryFileFactory $fileFactory,
        private readonly MediaLibraryFolderFactory $folderFactory
    ) {
    }

    public function countChildren(string $folder): int
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMust(new Prefix([$this->getConfig()->fieldFolder => $folder]));
        $search = $this->buildSearch($query);
        $search->setSize(0);

        return $this->elasticaService->count($search);
    }

    public function createFile(MediaLibraryFile $file): ?MediaLibraryFile
    {
        $createdUuid = $this->create($file);

        return $createdUuid ? $this->getFile($createdUuid) : null;
    }

    public function createFolder(MediaLibraryFolder $folder): ?MediaLibraryFolder
    {
        $createdUuid = $this->create($folder);

        return $createdUuid ? $this->getFolder($createdUuid) : null;
    }

    public function deleteDocument(MediaLibraryDocument $mediaDocument, ?string $username = null): void
    {
        $document = $mediaDocument->document;
        $this->dataService->delete($document->getContentType(), $document->getOuuid(), $username);
    }

    public function exists(MediaLibraryFile|MediaLibraryFolder $document): bool
    {
        $query = $this->elasticaService->getBoolQuery();
        $query
            ->addMust((new Term())->setTerm($this->getConfig()->fieldPath, $document->path))
            ->addMustNot((new Term())->setTerm('_id', $document->id));

        $existsFile = (new NestedQuery())
            ->setPath($this->getConfig()->fieldFile)
            ->setQuery(new Exists($this->getConfig()->fieldFile));

        match (true) {
            $document instanceof MediaLibraryFile => $query->addMust($existsFile),
            $document instanceof MediaLibraryFolder => $query->addMustNot($existsFile)
        };

        $search = $this->buildSearch($query, false);
        $count = $this->elasticaService->count($search);

        return !(0 === $count);
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
        return $this->fileFactory->createFromOuuid($this->getConfig(), $ouuid);
    }

    public function getFolder(string $ouuid): MediaLibraryFolder
    {
        return $this->folderFactory->createFromOuuid($this->getConfig(), $ouuid);
    }

    public function getFolders(): MediaLibraryFolders
    {
        $query = $this->elasticaService->getBoolQuery();
        $query->addMustNot(
            (new NestedQuery())
                ->setPath($this->getConfig()->fieldFile)
                ->setQuery(new Exists($this->getConfig()->fieldFile))
        );
        $query->addMust(new Exists($this->getConfig()->fieldPath));
        $query->addMust(new Exists($this->getConfig()->fieldFolder));

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

        $command = \vsprintf("%s --hash=%s --username='%s' -- %s", [
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

        $command = \vsprintf("%s --hash=%s --username='%s' -- %s '%s'", [
            Commands::MEDIA_LIB_FOLDER_RENAME,
            $this->getConfig()->getHash(),
            $user->getUserIdentifier(),
            $folder->id,
            $folder->giveName(),
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

    public function moveFile(MediaLibraryFile $file, ?MediaLibraryFolder $folder): void
    {
        $moveLocation = $folder ? $folder->getPath()->getValue() : '/';
        $newPath = $file->getPath()->move($moveLocation);
        $file->setPath($newPath);
    }

    public function newFile(?MediaLibraryFolder $parentFolder): MediaLibraryFile
    {
        return $this->fileFactory->create($this->getConfig(), $parentFolder);
    }

    public function newFolder(?MediaLibraryFolder $parentFolder): MediaLibraryFolder
    {
        return $this->folderFactory->create($this->getConfig(), $parentFolder);
    }

    private function refresh(): void
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
     * @return array<mixed>
     */
    public function renderFiles(int $from, ?MediaLibraryFolder $folder = null, ?string $sortId = null, ?string $sortOrder = null, int $selectionFiles = 0, ?string $searchValue = null): array
    {
        $findFiles = $this->findFiles(
            from: $from,
            folder: $folder,
            sortId: $sortId,
            sortOrder: $sortOrder,
            searchValue: $searchValue
        );

        $template = $this->templateFactory->create($this->getConfig(), \array_filter([
            'folder' => $folder,
            'mediaFiles' => $findFiles['files'],
        ]));

        return \array_filter([
            ...[
                'totalRows' => $findFiles['total_documents'],
                'remaining' => ($from + $findFiles['total_documents'] < $findFiles['total']),
                'rowHeader' => 0 === $from ? $template->block('media_lib_file_header_row') : null,
                'rows' => $template->block('media_lib_file_rows'),
                'sort' => $findFiles['sort'] ?? null,
            ],
            ...$this->renderLayout(
                loaded: ($from + $findFiles['total_documents']),
                folder: $folder,
                selectionFiles: $selectionFiles,
                searchValue: $searchValue
            ),
        ]);
    }

    public function renderFolders(): string
    {
        $folders = $this->getFolders();
        $template = $this->templateFactory->create($this->getConfig(), ['structure' => $folders->getStructure()]);

        return $template->block('media_lib_folder_rows');
    }

    /**
     * @return array{header: string, footer: string}
     */
    public function renderLayout(int $loaded, MediaLibraryFolder|string|null $folder = null, MediaLibraryFile|string|null $file = null, int $selectionFiles = 0, ?string $searchValue = null): array
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
            'mediaInfo' => $this->getInfo($loaded, $mediaFolder, $searchValue),
        ], static fn ($v) => null !== $v));

        return [
            'header' => $template->block('media_lib_header'),
            'footer' => $template->block('media_lib_footer'),
        ];
    }

    public function setConfig(MediaLibraryConfig $config): void
    {
        $this->config = $config;
    }

    public function updateDocument(MediaLibraryDocument $mediaDocument, ?string $username = null): void
    {
        $document = $mediaDocument->document;

        $this->revisionService->updateRawDataByEmsLink(
            emsLink: $document->getEmsLink(),
            rawData: $document->getSource(true),
            username: $username
        );

        $this->refresh();
    }

    private function buildSearch(BoolQuery $query, bool $includeSearchQuery = true): Search
    {
        if ($includeSearchQuery && $this->getConfig()->searchQuery) {
            $query->addMust($this->getConfig()->searchQuery);
        }

        $search = new Search([$this->getConfig()->contentType->giveEnvironment()->getAlias()], $query);
        $search->setContentTypes([$this->getConfig()->contentType->getName()]);

        return $search;
    }

    private function buildFileSearch(?MediaLibraryFolder $folder = null, ?string $searchValue = null): Search
    {
        $path = $folder ? $folder->getPath()->getValue().'/' : '/';
        $hashField = \sprintf('%s.%s', $this->getConfig()->fieldFile, EmsFields::CONTENT_FILE_HASH_FIELD);

        $query = $this->elasticaService->getBoolQuery();
        $query
            ->addMust((new NestedQuery())->setPath($this->getConfig()->fieldFile)->setQuery(new Exists($hashField)))
            ->addMust((new Term())->setTerm($this->getConfig()->fieldFolder, $path));

        $search = $this->buildSearch($query);

        if ($searchValue) {
            $search->setPostFilter($this->buildSearchValueQuery($searchValue));
        }

        return $search;
    }

    private function buildSearchValueQuery(string $searchValue): AbstractQuery
    {
        $jsonSearchFileQuery = Json::encode($this->getConfig()->searchFileQuery);

        $searchFileQuery = Json::decode(u($jsonSearchFileQuery)
            ->replace('%query%', Json::escape($searchValue))
            ->replace('%query_escaped%', Json::escape(QueryStringEscaper::escape($searchValue)))
            ->toString());

        if (!isset($searchFileQuery['bool'])) {
            throw new \RuntimeException('Search file query search should be a bool query');
        }

        $query = new BoolQuery();
        $query->setParams($searchFileQuery['bool']);

        return $query;
    }

    /**
     * @return array<mixed>
     */
    private function getInfo(int $loaded, ?MediaLibraryFolder $folder, ?string $searchValue = null): array
    {
        $search = $this->buildFileSearch($folder, $searchValue);
        $search->setSize(0);

        $fileField = $this->getConfig()->fieldFile;
        $fileSizeField = \sprintf('%s.%s', $fileField, EmsFields::CONTENT_FILE_SIZE_FIELD);

        $filesAgg = new NestedAgg('files', $this->getConfig()->fieldFile);
        $filesAgg->addAggregation((new Sum('size'))->setField($fileSizeField));
        $search->addAggregation($filesAgg);

        if ($searchValue) {
            $searchAgg = new Filter('search', $this->buildSearchValueQuery($searchValue));
            $searchAgg->addAggregation($filesAgg);
            $search->addAggregation($searchAgg);
        }

        $result = Response::fromResultSet($this->elasticaService->search($search));
        $resultFiles = $result->getAggregation('files');
        $resultSearch = $result->getAggregation('search');

        return \array_filter([
            'loaded' => $loaded,
            'folderTotal' => $resultFiles?->getCount(),
            'folderSize' => $resultFiles?->getRaw()['size']['value'] ?? null,
            'searchTotal' => $resultSearch?->getCount(),
            'searchSize' => $resultSearch?->getRaw()['files']['size']['value'] ?? null,
        ], static fn ($v) => null !== $v);
    }

    private function create(MediaLibraryDocument $mediaLibDocument): ?string
    {
        $uuid = Uuid::fromString($mediaLibDocument->id);
        $rawData = $mediaLibDocument->document->getSource();

        $revision = $this->revisionService->create($this->getConfig()->contentType, $uuid, $rawData);

        $form = $this->revisionService->createRevisionForm($revision);
        $this->dataService->finalizeDraft($revision, $form);

        $this->refresh();

        return 0 === $form->getErrors(true)->count() ? $uuid->toString() : null;
    }

    /**
     * @return array{
     *     files: MediaLibraryFile[],
     *     total: int,
     *     total_documents: int,
     *     sort: null|array{id: string, order: string}
     * }
     */
    private function findFiles(int $from, ?MediaLibraryFolder $folder, ?string $sortId = null, ?string $sortOrder = null, ?string $searchValue = null): array
    {
        $search = $this->buildFileSearch($folder, $searchValue);
        $search->setFrom($from);
        $search->setSize($this->getConfig()->searchSize);

        if ($configSort = $this->getConfig()->getSort($sortId)) {
            $searchOrder = $configSort->getOrder($sortOrder);
            $search->setSort($configSort->getQuery($searchOrder));
            $sort = ['id' => $configSort->id, 'order' => $searchOrder];
        }

        $result = Response::fromResultSet($this->elasticaService->search($search));

        return [
            'files' => $this->fileFactory->createFromDocumentCollection($this->getConfig(), $result->getDocumentCollection()),
            'sort' => $sort ?? null,
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
