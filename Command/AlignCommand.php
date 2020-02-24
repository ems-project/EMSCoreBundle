<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AlignCommand extends EmsCommand
{
    protected $doctrine;
    protected $data;
    /**@var ContentTypeService */
    private $contentTypeService;
    /**@var EnvironmentService */
    private $environmentService;
    /**@var PublishService */
    private $publishService;

    const DEFAULT_SCROLL_SIZE = '100';
    const DEFAULT_SCROLL_TIMEOUT = '1m';

    public function __construct(Registry $doctrine, LoggerInterface $logger, Client $client, DataService $data, ContentTypeService $contentTypeService, EnvironmentService $environmentService, PublishService $publishService)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->client = $client;
        $this->data = $data;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->publishService = $publishService;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this->logger->info('Configure the AlignCommand');
        $this
            ->setName('ems:environment:align')
            ->setDescription('Align an environment from another one')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Environment source name'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Environment target name'
            )
            ->addArgument(
                'scrollSize',
                InputArgument::OPTIONAL,
                'Size of the elasticsearch scroll request',
                self::DEFAULT_SCROLL_SIZE
            )
            ->addArgument(
                'scrollTimeout',
                InputArgument::OPTIONAL,
                'Time to migrate "scrollSize" items i.e. 30s or 2m',
                self::DEFAULT_SCROLL_TIMEOUT
            )
            ->addArgument(
                'contentType',
                InputArgument::OPTIONAL,
                'The content type you wish to align'
            )
            ->addOption(
                'searchQuery',
                null,
                InputOption::VALUE_OPTIONAL,
                'Query used to find elasticsearch records to import',
                ''
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'If set, the task will be performed (protection)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->formatStyles($output);
        
        if (! $input->getOption('force')) {
            $output->writeln('<error>Has protection, the force option is mandatory</error>');
            return -1;
        }

        $sourceName = $input->getArgument('source');
        $targetName = $input->getArgument('target');
        $scrollSize = $input->getArgument('scrollSize');
        $scrollTimeout = $input->getArgument('scrollTimeout');
        $searchQuery = $input->getOption('searchQuery');
        $contentType = $input->getArgument('contentType');

        $source = $this->environmentService->getAliasByName($sourceName);
        $target = $this->environmentService->getAliasByName($targetName);

        if (!$source) {
            $output->writeln('<error>Source ' . $sourceName . ' not found</error>');
        }
        if (!$target) {
            $output->writeln('<error>Target ' . $targetName . ' not found</error>');
        }

        if (! $source || ! $target) {
            return -1;
        }

        if ($source === $target) {
            $output->writeln('<error>Target and source are the same environment, it\'s aligned ;-)</error>');
            return -1;
        }

        $this->logger->info('Execute the AlignCommand');

        $arrayElasticsearchIndex = $this->client->search([
            'index' => $source->getAlias(),
            'type' => $contentType,
            'size' => $scrollSize,
            'scroll' => $scrollTimeout,
            'body' => $searchQuery,
        ]);

        $total = $arrayElasticsearchIndex['hits']['total'];

        $output->writeln('The source environment contains ' . $total . ' elements, start aligning environments...');

        $progress = new ProgressBar($output, $total);
        $progress->start();

        $deletedRevision = 0;
        $alreadyAligned = 0;
        $targetIsPreviewEnvironment = [];

        while (isset($arrayElasticsearchIndex['hits']['hits']) && count($arrayElasticsearchIndex['hits']['hits']) > 0) {
            $flush = false;
            foreach ($arrayElasticsearchIndex['hits']['hits'] as $hit) {
                $revision = $this->data->getRevisionByEnvironment($hit['_id'], $this->contentTypeService->getByName($hit['_type']), $source);
                if ($revision->getDeleted()) {
                    ++$deletedRevision;
                } else if ($revision->getContentType()->getEnvironment() === $target) {
                    if (!isset($targetIsPreviewEnvironment[$revision->getContentType()->getName()])) {
                        $targetIsPreviewEnvironment[$revision->getContentType()->getName()] = 0;
                    }
                    ++$targetIsPreviewEnvironment[$revision->getContentType()->getName()];
                } else {
                    if ($this->publishService->publish($revision, $target, true) == 0) {
                        ++$alreadyAligned;
                        $flush = true;
                    }
                }
                $progress->advance();
            }

            if ($flush) {
                $output->writeln("");
            }

            $arrayElasticsearchIndex = $this->client->scroll([
                'scroll_id' => $arrayElasticsearchIndex['_scroll_id'],
                'scroll' => $scrollTimeout,
            ]);
        }

        $progress->finish();
        $output->writeln('');
        if ($deletedRevision) {
            $output->writeln('<error>' . $deletedRevision . ' deleted revisions were not aligned</error>');
        }
        if ($alreadyAligned) {
            $output->writeln('' . $alreadyAligned . ' revisions were already aligned');
        }
        foreach ($targetIsPreviewEnvironment as $ctName => $counter) {
            $output->writeln('<error>' . $counter . ' ' . $ctName . ' revisions were not aligned as ' . $targetName . ' is the default environment</error>');
        }

        $output->writeln('Environments are aligned.');
        return 0;
    }
}
