<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Service\DocumentService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateCommand extends Command
{
    protected static $defaultName = 'ems:contenttype:migrate';

    private RevisionService $revisionService;
    private ElasticaService $elasticaService;
    protected Registry $doctrine;
    private DocumentService $documentService;

    private string $elasticsearchIndex;
    private string $contentTypeNameFrom;
    private string $contentTypeNameTo;
    private int  $scrollSize;
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
    private ContentTypeRepository $contentTypeRepository;
    private SymfonyStyle  $io;

    private const ARGUMENT_CONTENTTYPE_NAME_FROM = 'contentTypeNameFrom';
    private const ARGUMENT_CONTENTTYPE_NAME_TO = 'contentTypeNameTo';
    private const ARGUMENT_SCROLL_SIZE = 'scrollSize';
    private const ARGUMENT_SCROLL_TIMEOUT = 'scrollTimeout';
    private const ARGUMENT_ELASTICSEARCH_INDEX = 'elasticsearchIndex';

    public function __construct(
        RevisionService $revisionService,
        Registry $doctrine,
        ElasticaService $elasticaService,
        DocumentService $documentService)
    {
        $this->revisionService = $revisionService;
        $this->doctrine = $doctrine;
        $this->elasticaService = $elasticaService;
        $this->documentService = $documentService;

        $em = $this->doctrine->getManager();
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');
        if (!$contentTypeRepository instanceof ContentTypeRepository) {
            throw new \Exception('Wrong ContentTypeRepository repository instance');
        }

        $this->contentTypeRepository = $contentTypeRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Migrate a content type from an elasticsearch index')
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
                'bulkSize',
                null,
                InputOption::VALUE_OPTIONAL,
                'Size of the elasticsearch bulk request',
                '500'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Allow to import from the default environment and to draft revision'
            )
            ->addOption(
                'raw',
                null,
                InputOption::VALUE_NONE,
                'The content will be imported as is. Without any field validation, data stripping or field protection'
            )
            ->addOption(
                'sign-data',
                null,
                InputOption::VALUE_NONE,
                'The content will be (re)signed during the reindexing process'
            )
            ->addOption(
                'searchQuery',
                null,
                InputOption::VALUE_OPTIONAL,
                'Query used to find elasticsearch records to import',
                '{"sort":{"_uid":{"order":"asc"}}}'
            )
            ->addOption(
                'dont-finalize',
                null,
                InputOption::VALUE_NONE,
                'Don\'t finalize document'
            )
            ->addOption(
                'changed',
                null,
                InputOption::VALUE_NONE,
                'Will only migrate if the hash is different, If equal it will only update the modified dateTime of the current revision'
            )
            ->addOption(
                'archive',
                null,
                InputOption::VALUE_NONE,
                'Will archive revisions that were not modified (see changed option)'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

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

        $options = \array_values($input->getOptions());
        list($this->bulkSize, $this->forceImport, $this->rawImport, $this->signData, $this->searchQuery, $this->dontFinalize, $this->onlyChanged) = $options;

        $contentTypeTo = $this->contentTypeRepository->findByName($this->contentTypeNameTo);
        if (null === $contentTypeTo || !$contentTypeTo instanceof ContentType) {
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

        $archive = \boolval($input->getOption('archive'));
        if ($archive) {
            $this->archiveModifiedBefore = DateTime::create('now');
            $this->io->note(\sprintf('Will archive not updated revisions before %s', $this->archiveModifiedBefore->format(\DateTimeInterface::ATOM)));
        }

        return 0;
    }

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
                if (false === $result) {
                    continue;
                }
                try {
                    $this->documentService->importDocument($importerContext, $result->getId(), $result->getSource());
                } catch (NotLockedException $e) {
                    $this->io->error($e);
                } catch (CantBeFinalizedException $e) {
                    $this->io->error($e);
                }
                $progress->advance();
            }
            $this->documentService->flushAndSend($importerContext);
        }
        $progress->finish();

        if (null !== $archiveModifiedBefore = $this->archiveModifiedBefore) {
            $this->archive($output, $archiveModifiedBefore->format(\DateTimeInterface::ATOM));
        }

        $this->io->writeln('');
        $this->io->writeln('Migration done');

        return 0;
    }

    private function archive(OutputInterface $output, string $archiveModifiedBefore): int
    {
        try {
            if (null === $application = $this->getApplication()) {
                throw new \RuntimeException('could not find application');
            }

            return $application->find('ems:revision:archive')->run(
                new ArrayInput([
                    'content-type' => $this->contentTypeTo->getName(),
                    '--force' => true,
                    '--modified-before' => $archiveModifiedBefore,
                ]),
                $output
            );
        } catch (\Throwable $e) {
            $this->io->error(\sprintf('Archived failed! (%s)', $e->getMessage()));

            return 0;
        }
    }
}
