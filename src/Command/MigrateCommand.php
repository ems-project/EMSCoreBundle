<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Command\Revision\ArchiveCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Service\DocumentService;
use EMS\Helpers\Standard\DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::CONTENT_TYPE_MIGRATE,
    description: 'Migrate a content type from an elasticsearch index.',
    hidden: false,
    aliases: ['ems:contenttype:migrate']
)]
class MigrateCommand extends AbstractCommand
{
    private string $elasticsearchIndex;
    private string $contentTypeNameFrom;
    private string $contentTypeNameTo;
    private int $scrollSize;
    private string $scrollTimeout;
    private bool $indexInDefaultEnv;

    private Environment $defaultEnv;
    private ContentType $contentTypeTo;
    private int $bulkSize;
    private bool $forceImport;
    private bool $rawImport;
    private bool $onlyChanged;
    private ?\DateTimeInterface $archiveModifiedBefore = null;
    private bool $signData;
    private string $searchQuery;
    private bool $dontFinalize;
    private readonly ContentTypeRepository $contentTypeRepository;

    private const string ARGUMENT_CONTENTTYPE_NAME_FROM = 'contentTypeNameFrom';
    private const string ARGUMENT_CONTENTTYPE_NAME_TO = 'contentTypeNameTo';
    private const string ARGUMENT_SCROLL_SIZE = 'scrollSize';
    private const string ARGUMENT_SCROLL_TIMEOUT = 'scrollTimeout';
    private const string ARGUMENT_ELASTICSEARCH_INDEX = 'elasticsearchIndex';

    private const string OPTION_ARCHIVE = 'archive';
    private const string OPTION_CHANGED = 'changed';
    private const string OPTION_DONT_FINALIZE = 'dont-finalize';
    private const string OPTION_FORCE = 'force';
    private const string OPTION_BULK_SIZE = 'bulkSize';
    private const string OPTION_RAW = 'raw';
    private const string OPTION_SIGN_DATA = 'sign-data';
    private const string OPTION_SEARCH_QUERY = 'searchQuery';

    public function __construct(
        protected Registry $doctrine,
        private readonly ElasticaService $elasticaService,
        private readonly DocumentService $documentService)
    {
        $em = $this->doctrine->getManager();
        $contentTypeRepository = $em->getRepository(ContentType::class);
        if (!$contentTypeRepository instanceof ContentTypeRepository) {
            throw new \Exception('Wrong ContentTypeRepository repository instance');
        }

        $this->contentTypeRepository = $contentTypeRepository;
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARGUMENT_ELASTICSEARCH_INDEX,
                InputArgument::REQUIRED,
                'Elasticsearch index where to find ContentType objects as new source'
            )
            ->addArgument(
                self::ARGUMENT_CONTENTTYPE_NAME_FROM,
                InputArgument::REQUIRED,
                'Content type name to migrate from'
            )
            ->addArgument(
                self::ARGUMENT_CONTENTTYPE_NAME_TO,
                InputArgument::OPTIONAL,
                'Content type name to migrate into (default same as from)'
            )
            ->addArgument(
                self::ARGUMENT_SCROLL_SIZE,
                InputArgument::OPTIONAL,
                'Size of the elasticsearch scroll request',
                '100'
            )
            ->addArgument(
                self::ARGUMENT_SCROLL_TIMEOUT,
                InputArgument::OPTIONAL,
                'Time to migrate "scrollSize" items i.e. 30s or 2m',
                '1m'
            )
            ->addOption(
                self::OPTION_BULK_SIZE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Size of the elasticsearch bulk request',
                '500'
            )
            ->addOption(
                self::OPTION_FORCE,
                null,
                InputOption::VALUE_NONE,
                'Allow to import from the default environment and to draft revision'
            )
            ->addOption(
                self::OPTION_RAW,
                null,
                InputOption::VALUE_NONE,
                'The content will be imported as is. Without any field validation, data stripping or field protection'
            )
            ->addOption(
                self::OPTION_SIGN_DATA,
                null,
                InputOption::VALUE_NONE,
                'The content will be (re)signed during the reindexing process'
            )
            ->addOption(
                self::OPTION_SEARCH_QUERY,
                null,
                InputOption::VALUE_OPTIONAL,
                'Query used to find elasticsearch records to import',
                '{"sort":{"_uid":{"order":"asc"}}}'
            )
            ->addOption(
                self::OPTION_DONT_FINALIZE,
                null,
                InputOption::VALUE_NONE,
                'Don\'t finalize document'
            )
            ->addOption(
                self::OPTION_CHANGED,
                null,
                InputOption::VALUE_NONE,
                'Will only migrate if the hash is different, If equal it will only update the modified dateTime of the current revision'
            )
            ->addOption(
                self::OPTION_ARCHIVE,
                null,
                InputOption::VALUE_NONE,
                'Will archive revisions that were not modified (see changed option)'
            )
        ;
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Start migration');
        $this->io->section('Checking input');

        $elasticsearchIndex = $input->getArgument(self::ARGUMENT_ELASTICSEARCH_INDEX);
        if (!\is_string($elasticsearchIndex)) {
            throw new \RuntimeException('Unexpected index name');
        }
        $this->elasticsearchIndex = $elasticsearchIndex;
        $contentTypeNameFrom = $input->getArgument(self::ARGUMENT_CONTENTTYPE_NAME_FROM);
        if (!\is_string($contentTypeNameFrom)) {
            throw new \RuntimeException('Unexpected Content type From name');
        }
        $this->contentTypeNameFrom = $contentTypeNameFrom;
        $contentTypeNameTo = $input->getArgument(self::ARGUMENT_CONTENTTYPE_NAME_TO);
        if (null === $contentTypeNameTo) {
            $contentTypeNameTo = $this->contentTypeNameFrom;
        }
        if (!\is_string($contentTypeNameTo)) {
            throw new \RuntimeException('Unexpected Content type To name');
        }
        $this->contentTypeNameTo = $contentTypeNameTo;
        $this->scrollSize = \intval($input->getArgument(self::ARGUMENT_SCROLL_SIZE));
        if (0 === $this->scrollSize) {
            throw new \RuntimeException('Unexpected scroll size argument');
        }
        $scrollTimeout = $input->getArgument(self::ARGUMENT_SCROLL_TIMEOUT);
        if (!\is_string($scrollTimeout)) {
            throw new \RuntimeException('Unexpected scroll timeout argument');
        }
        $this->scrollTimeout = $scrollTimeout;

        $this->bulkSize = (int) $input->getOption(self::OPTION_BULK_SIZE);
        $this->forceImport = (bool) $input->getOption(self::OPTION_FORCE);
        $this->rawImport = (bool) $input->getOption(self::OPTION_RAW);
        $this->signData = (bool) $input->getOption(self::OPTION_SIGN_DATA);
        $this->searchQuery = $input->getOption(self::OPTION_SEARCH_QUERY);
        $this->dontFinalize = (bool) $input->getOption(self::OPTION_DONT_FINALIZE);
        $this->onlyChanged = (bool) $input->getOption(self::OPTION_CHANGED);

        $contentTypeTo = $this->contentTypeRepository->findByName($this->contentTypeNameTo);
        if (null === $contentTypeTo) {
            $this->io->error(\sprintf('Content type "%s" not found', $this->contentTypeNameTo));

            return -1;
        }
        $this->contentTypeTo = $contentTypeTo;
        $defaultEnv = $this->contentTypeTo->getEnvironment();
        if (null === $defaultEnv) {
            throw new \RuntimeException('Unexpected null environment');
        }
        $this->defaultEnv = $defaultEnv;

        if ($this->contentTypeTo->getDirty()) {
            $this->io->error(\sprintf('Content type "%s" is dirty. Please clean it first', $this->contentTypeNameTo));

            return -1;
        }

        $this->indexInDefaultEnv = true;
        if (0 === \strcmp($this->defaultEnv->getAlias(), $this->elasticsearchIndex) && 0 === \strcmp($this->contentTypeNameFrom, $this->contentTypeNameTo)) {
            if (!$this->forceImport) {
                $this->io->error('You can not import a content type on himself with the --force option');

                return -1;
            }
            $this->indexInDefaultEnv = false;
        }

        $archive = \boolval($input->getOption(self::OPTION_ARCHIVE));
        if ($archive) {
            $this->archiveModifiedBefore = DateTime::create('now');
            $this->io->note(\sprintf('Will archive not updated revisions before %s', $this->archiveModifiedBefore->format(\DateTimeInterface::ATOM)));
        }

        return 0;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section(\sprintf('Start migration of %s', $this->contentTypeTo->getPluralName()));

        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $this->elasticsearchIndex,
            'type' => $this->contentTypeNameFrom,
            'size' => $this->scrollSize,
            'body' => $this->searchQuery,
        ]);

        $scroll = $this->elasticaService->scroll($search, $this->scrollTimeout);
        $total = $this->elasticaService->count($search);

        $progress = $this->io->createProgressBar($total);
        $importerContext = $this->documentService->initDocumentImporterContext($this->contentTypeTo, 'SYSTEM_MIGRATE', $this->rawImport, $this->signData, $this->indexInDefaultEnv, $this->bulkSize, !$this->dontFinalize, $this->forceImport);
        $importerContext->setShouldOnlyChanged($this->onlyChanged);

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                try {
                    $this->documentService->importDocument($importerContext, $result->getId(), $result->getSource());
                } catch (NotLockedException|CantBeFinalizedException $e) {
                    $this->io->error($e);
                }
                $progress->advance();
            }
            $this->documentService->flushAndSend($importerContext);
        }
        $progress->finish();

        if (null !== $archiveModifiedBefore = $this->archiveModifiedBefore) {
            $this->archive($archiveModifiedBefore);
        }

        $this->io->writeln('');
        $this->io->writeln('Migration done');

        return 0;
    }

    private function archive(\DateTimeInterface $archiveModifiedBefore): void
    {
        $arguments = [ArchiveCommand::ARGUMENT_CONTENT_TYPE => $this->contentTypeTo->getName()];
        $options = [
            ArchiveCommand::OPTION_FORCE => true,
            ArchiveCommand::OPTION_MODIFIED_BEFORE => $archiveModifiedBefore->format(\DateTimeInterface::ATOM),
        ];

        $this->runCommand(Commands::REVISION_ARCHIVE, $arguments, $options);
    }
}
