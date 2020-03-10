<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Service\ImportService;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends EmsCommand
{
    /** @var Client  */
    protected $client;

    /** @var Registry  */
    protected $doctrine;

    /** @var ImportService */
    private $importService;

    public function __construct(Registry $doctrine, Logger $logger, Client $client, ImportService $importService)
    {
        $this->doctrine = $doctrine;
        $this->client = $client;
        $this->importService = $importService;
        parent::__construct($logger, $client);
    }
    
    protected function configure()
    {
        $this
            ->setName('ems:contenttype:migrate')
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
            ->addOption(
                'bulkSize',
                null,
                InputOption::VALUE_OPTIONAL,
                'Size of the elasticsearch bulk request',
                500
            )
            ->addArgument(
                'scrollTimeout',
                InputArgument::OPTIONAL,
                'Time to migrate "scrollSize" items i.e. 30s or 2m',
                '1m'
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $elasticsearchIndex = $input->getArgument('elasticsearchIndex');
        $contentTypeNameFrom = $input->getArgument('contentTypeNameFrom');
        $contentTypeNameTo = $input->getArgument('contentTypeNameTo');
        $scrollSize = $input->getArgument('scrollSize');
        $bulkSize = $input->getOption('bulkSize');
        $scrollTimeout = $input->getArgument('scrollTimeout');
        $rawImport = $input->getOption('raw');
        $forceImport = $input->getOption('force');
        $signData = $input->getOption('sign-data');
        $searchQuery = $input->getOption('searchQuery');
        $finalize = !$input->getOption('dont-finalize');

        if ($contentTypeNameTo === null) {
            $contentTypeNameTo = $contentTypeNameFrom;
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

        /** @var ContentType|null $contentTypeTo */
        $contentTypeTo = $contentTypeRepository->findOneBy(array("name" => $contentTypeNameTo, 'deleted' => false));
        if ($contentTypeTo === null) {
            $output->writeln("<error>Content type " . $contentTypeNameTo . " not found</error>");
            exit;
        }
        $defaultEnv = $contentTypeTo->getEnvironment();
        
        $output->writeln("Start migration of " . $contentTypeTo->getPluralName());
        
        if ($contentTypeTo->getDirty()) {
            $output->writeln("<error>Content type \"" . $contentTypeNameTo . "\" is dirty. Please clean it first</error>");
            exit;
        }
        
        $indexInDefaultEnv = true;
        if (strcmp($defaultEnv->getAlias(), $elasticsearchIndex) === 0 && strcmp($contentTypeNameFrom, $contentTypeNameTo) === 0) {
            if (!$forceImport) {
                $output->writeln("<error>You can not import a content type on himself with the --force option</error>");
                exit;
            }
            $indexInDefaultEnv = false;
        }

        $arrayElasticsearchIndex = $this->client->search([
                'index' => $elasticsearchIndex,
                'type' => $contentTypeNameFrom,
                'size' => $scrollSize,
                "scroll" => $scrollTimeout,
                'body' => $searchQuery,
        ]);

        $progress = new ProgressBar($output, $arrayElasticsearchIndex["hits"]["total"]);
        $progress->start();
        $importer = $this->importService->initDocumentImporter($contentTypeTo, 'SYSTEM_MIGRATE', $rawImport, $signData, $indexInDefaultEnv, $bulkSize, $finalize, $forceImport);
        
        while (isset($arrayElasticsearchIndex['hits']['hits']) && count($arrayElasticsearchIndex['hits']['hits']) > 0) {
            foreach ($arrayElasticsearchIndex["hits"]["hits"] as $index => $value) {
                try {
                    $importer->importDocument($value['_id'], $value['_source']);
                } catch (NotLockedException $e) {
                    $output->writeln("<error>'.$e.'</error>");
                } catch (CantBeFinalizedException $e) {
                    $output->writeln("<error>'.$e.'</error>");
                }
                $progress->advance();
            }
            $importer->clearAndSend();

            $arrayElasticsearchIndex = $this->client->scroll([
                'scroll_id' => $arrayElasticsearchIndex['_scroll_id'],
                'scroll' => $scrollTimeout,
            ]);
        }
        $progress->finish();
        $output->writeln("");
        $output->writeln("Migration done");
    }
}
