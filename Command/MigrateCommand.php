<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Service\DocumentService;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateCommand extends Command
{
    protected static $defaultName = 'ems:contenttype:migrate';

    /** @var Client  */
    protected $client;

    /** @var Registry  */
    protected $doctrine;

    /** @var DocumentService */
    private $documentService;

    /** @var string */
    private $elasticsearchIndex;

    /** @var string */
    private $contentTypeNameFrom;

    /** @var string */
    private $contentTypeNameTo;

    /** @var int */
    private $scrollSize;

    /** @var string */
    private $scrollTimeout;

    /** @var boolean */
    private $ready;

    /** @var boolean */
    private $indexInDefaultEnv;

    /** @var Environment */
    private $defaultEnv;

    /** @var ContentType */
    private $contentTypeTo;

    /** @var int */
    private $bulkSize;

    /** @var bool */
    private $forceImport;

    /** @var bool */
    private $rawImport;

    /** @var bool */
    private $signData;

    /** @var string */
    private $searchQuery;

    /** @var bool */
    private $finalize;

    /** @var Logger */
    private $logger;

    /** @var ContentTypeRepository */
    private $contentTypeRepository;

    /** @var SymfonyStyle */
    private $io;

    public function __construct(Registry $doctrine, Logger $logger, Client $client, DocumentService $documentService)
    {
        $this->doctrine = $doctrine;
        $this->ready = false;
        $this->client = $client;
        $this->logger = $logger;
        $this->documentService = $documentService;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Migrate a content type from an elasticsearch index')
            ->addArgument(
                'elasticsearchIndex',
                InputArgument::REQUIRED,
                'Elasticsearch index where to find ContentType objects as new source'
            )
            ->addArgument(
                'contentTypeNameFrom',
                InputArgument::REQUIRED,
                'Content type name to migrate from'
            )
            ->addArgument(
                'contentTypeNameTo',
                InputArgument::OPTIONAL,
                'Content type name to migrate into (default same as from)'
            )
            ->addArgument(
                'scrollSize',
                InputArgument::OPTIONAL,
                'Size of the elasticsearch scroll request',
                100
            )
            ->addArgument(
                'scrollTimeout',
                InputArgument::OPTIONAL,
                'Time to migrate "scrollSize" items i.e. 30s or 2m',
                '1m'
            )
            ->addOption(
                'bulkSize',
                null,
                InputOption::VALUE_OPTIONAL,
                'Size of the elasticsearch bulk request',
                500
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Allow to import from the default environment and/or to cancel draft revision'
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
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $em = $this->doctrine->getManager();
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');
        if (! $contentTypeRepository instanceof ContentTypeRepository) {
            throw new \Exception('Wrong ContentTypeRepository repository instance');
        }

        $this->contentTypeRepository = $contentTypeRepository;
    }


    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->io->title('Start migration');
        $this->io->section('Checking input');
        $arguments = array_values($input->getArguments());
        array_shift($arguments);
        list($this->elasticsearchIndex, $this->contentTypeNameFrom, $this->contentTypeNameTo, $this->scrollSize, $this->scrollTimeout) = $arguments;

        $options = array_values($input->getOptions());
        list($this->bulkSize, $this->forceImport, $this->rawImport, $this->signData, $this->searchQuery, $this->finalize) = $options;

        if ($this->contentTypeNameTo === null) {
            $this->contentTypeNameTo = $this->contentTypeNameFrom;
        }

        $contentTypeTo = $this->contentTypeRepository->findOneBy(array("name" => $this->contentTypeNameTo, 'deleted' => false));
        if ($contentTypeTo === null || !$contentTypeTo instanceof ContentType) {
            $this->io->error(sprintf('Content type "%s" not found', $this->contentTypeNameTo));
            return;
        }
        $this->contentTypeTo = $contentTypeTo;
        $this->defaultEnv = $this->contentTypeTo->getEnvironment();

        if ($this->contentTypeTo->getDirty()) {
            $this->io->error(sprintf('Content type "%s" is dirty. Please clean it first', $this->contentTypeNameTo));
            return;
        }

        $this->indexInDefaultEnv = true;
        if (strcmp($this->defaultEnv->getAlias(), $this->elasticsearchIndex) === 0 && strcmp($this->contentTypeNameFrom, $this->contentTypeNameTo) === 0) {
            if (!$this->forceImport) {
                $this->io->error('You can not import a content type on himself with the --force option');
                return;
            }
            $this->indexInDefaultEnv = false;
        }

        $this->ready = true;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->ready) {
            return -1;
        }

        $this->io->section(sprintf('Start migration of %s', $this->contentTypeTo->getPluralName()));

        $arrayElasticsearchIndex = $this->client->search([
                'index' => $this->elasticsearchIndex,
                'type' => $this->contentTypeNameFrom,
                'size' => $this->scrollSize,
                "scroll" => $this->scrollTimeout,
                'body' => $this->searchQuery,
        ]);

        $progress = $this->io->createProgressBar($arrayElasticsearchIndex["hits"]["total"]);
        $importer = $this->documentService->initDocumentImporter($this->contentTypeTo, 'SYSTEM_MIGRATE', $this->rawImport, $this->signData, $this->indexInDefaultEnv, $this->bulkSize, $this->finalize, $this->forceImport);
        
        while (isset($arrayElasticsearchIndex['hits']['hits']) && count($arrayElasticsearchIndex['hits']['hits']) > 0) {
            foreach ($arrayElasticsearchIndex["hits"]["hits"] as $index => $value) {
                try {
                    $importer->importDocument($value['_id'], $value['_source']);
                } catch (NotLockedException $e) {
                    $this->io->error($e);
                } catch (CantBeFinalizedException $e) {
                    $this->io->error($e);
                }
                $progress->advance();
            }
            $importer->flushAndSend();

            $arrayElasticsearchIndex = $this->client->scroll([
                'scroll_id' => $arrayElasticsearchIndex['_scroll_id'],
                'scroll' => $this->scrollTimeout,
            ]);
        }
        $progress->finish();
        $this->io->writeln("");
        $this->io->writeln("Migration done");
    }
}
