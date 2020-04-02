<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AlignCommand extends Command
{
    protected static $defaultName = 'ems:environment:align';

    /** @var Registry */
    protected $doctrine;

    /** @var LoggerInterface */
    private $logger;

    /** @var Client */
    private $client;

    /** @var DataService */
    protected $data;

    /**@var ContentTypeService */
    private $contentTypeService;

    /**@var EnvironmentService */
    private $environmentService;

    /**@var PublishService */
    private $publishService;

    /** @var SymfonyStyle */
    private $io;

    /** @var string */
    private $scrollSize;

    /** @var string */
    private $scrollTimeout;

    /** @var string */
    private $searchQuery;

    const ARGUMENT_SOURCE = 'source';
    const ARGUMENT_TARGET = 'target';
    const ARGUMENT_SCROLL_SIZE = 'scrollSize';
    const ARGUMENT_SCROLL_TIMEOUT = 'scrollTimeout';

    const OPTION_FORCE = 'force';
    const OPTION_SEARCH_QUERY = 'searchQuery';
    const OPTION_SNAPSHOT = 'snapshot';
    const OPTION_STRICT = 'strict';

    const DEFAULT_SCROLL_SIZE = '100';
    const DEFAULT_SCROLL_TIMEOUT = '1m';
    const DEFAULT_SEARCH_QUERY = '{}';

    public function __construct(Registry $doctrine, LoggerInterface $logger, Client $client, DataService $data, ContentTypeService $contentTypeService, EnvironmentService $environmentService, PublishService $publishService)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->client = $client;
        $this->data = $data;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->publishService = $publishService;

        parent::__construct();
    }

    protected function configure()
    {
        $this->logger->info('Configure the AlignCommand');

        $this
            ->setDescription('Align an environment from another one')
            ->addArgument(
                self::ARGUMENT_SOURCE,
                InputArgument::REQUIRED,
                'Environment source name'
            )
            ->addArgument(
                self::ARGUMENT_TARGET,
                InputArgument::REQUIRED,
                'Environment target name'
            )
            ->addArgument(
                self::ARGUMENT_SCROLL_SIZE,
                InputArgument::OPTIONAL,
                'Size of the elasticsearch scroll request',
                self::DEFAULT_SCROLL_SIZE
            )
            ->addArgument(
                self::ARGUMENT_SCROLL_TIMEOUT,
                InputArgument::OPTIONAL,
                'Time to migrate "scrollSize" items i.e. 30s or 2m',
                self::DEFAULT_SCROLL_TIMEOUT
            )
            ->addOption(
                self::OPTION_SEARCH_QUERY,
                null,
                InputOption::VALUE_OPTIONAL,
                'Query used to find elasticsearch records to import',
                self::DEFAULT_SEARCH_QUERY
            )
            ->addOption(
                self::OPTION_FORCE,
                null,
                InputOption::VALUE_NONE,
                'If set, the task will be performed (protection)'
            )
            ->addOption(
                self::OPTION_SNAPSHOT,
                null,
                InputOption::VALUE_NONE,
                'If set, the target environment will be tagged as a snapshot after the alignment'
            )
            ->addOption(
                self::OPTION_STRICT,
                null,
                InputOption::VALUE_NONE,
                'If set, a failed check will throw an exception'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Align environments');

        $this->scrollSize = $input->getArgument(self::ARGUMENT_SCROLL_SIZE);
        $this->scrollTimeout = $input->getArgument(self::ARGUMENT_SCROLL_TIMEOUT);
        $this->searchQuery = $input->getOption(self::OPTION_SEARCH_QUERY);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('Interact with AlignCommand');

        $this->io->section('Check inputs');
        $this->checkSource($input);
        $this->checkTarget($input);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Execute the AlignCommand');

        $this->io->section('Execute');
        if (!$input->getOption(self::OPTION_FORCE)) {
            $this->io->error('Has protection, the force option is mandatory.');
            return -1;
        }

        $sourceName = $input->getArgument(self::ARGUMENT_SOURCE);
        $targetName = $input->getArgument(self::ARGUMENT_TARGET);

        $this->environmentService->clearCache();
        $source = $this->environmentService->getAliasByName($sourceName);
        $target = $this->environmentService->getAliasByName($targetName);

        $arrayElasticsearchIndex = $this->client->search([
            'index' => $source->getAlias(),
            'size' => $this->scrollSize,
            'scroll' => $this->scrollTimeout,
            'body' => $this->searchQuery,
        ]);

        $total = $arrayElasticsearchIndex['hits']['total'];

        $this->io->note(\sprintf('The source environment contains %s elements, start aligning environments...', $total));

        $this->io->progressStart($total);

        $deletedRevision = 0;
        $alreadyAligned = 0;
        $targetIsPreviewEnvironment = [];

        while (count($arrayElasticsearchIndex['hits']['hits'] ?? []) > 0) {
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
                    }
                }
                $this->io->progressAdvance();
            }

            $arrayElasticsearchIndex = $this->client->scroll([
                'scroll_id' => $arrayElasticsearchIndex['_scroll_id'],
                'scroll' => $this->scrollTimeout,
            ]);
        }

        $this->io->progressFinish();

        if ($deletedRevision) {
            $this->io->caution(\sprintf('%s deleted revisions were not aligned', $deletedRevision));
        }

        if ($alreadyAligned) {
            $this->io->note(\sprintf('%s revisions were already aligned', $alreadyAligned));
        }

        foreach ($targetIsPreviewEnvironment as $ctName => $counter) {
            $this->io->caution(\sprintf('%s %s revisions were not aligned as %s is the default environment', $counter, $ctName, $targetName));
        }

        if ($input->getOption(self::OPTION_SNAPSHOT)) {
            $this->environmentService->setSnapshotTag($target);
            $this->io->note(\sprintf('The target environment "%s" was tagged as a snapshot', $targetName));
        }

        $this->io->success(\sprintf('Environments %s -> %s were aligned.', $sourceName, $targetName));
        return 0;
    }

    private function checkSource(InputInterface $input)
    {
        $sourceName = $input->getArgument(self::ARGUMENT_SOURCE);
        if ($sourceName === null) {
            $message = 'Source environment not provided';
            $this->setSourceArgument($input, $message);
            return;
        }

        $source = $this->environmentService->getAliasByName($sourceName);
        if ($source === false) {
            $message = \sprintf('Source environment "%s" not found', $sourceName);
            $this->setSourceArgument($input, $message);
            $this->checkSource($input);
            return;
        }

        $this->io->note(\sprintf('Continuing with the source environment "%s"', $sourceName));
    }

    private function setSourceArgument(InputInterface $input, $message)
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $sourceName = $this->io->choice('Select an existing environment as source', $this->environmentService->getEnvironmentNames());
        $input->setArgument(self::ARGUMENT_SOURCE, $sourceName);
    }

    private function checkTarget(InputInterface $input)
    {
        $targetName = $input->getArgument(self::ARGUMENT_TARGET);
        if ($targetName === null) {
            $message = 'Target environment not provided';
            $this->setTargetArgument($input, $message);
            return;
        }

        $this->environmentService->clearCache();
        $target = $this->environmentService->getByName($targetName);
        if ($target === false) {
            $message = \sprintf('Target environment "%s" not found', $targetName);
            $this->setTargetArgument($input, $message);
            $this->checkTarget($input);
            return;
        }

        if ($target->getSnapshot()) {
            $message = 'Target cannot be a snapshot';
            $this->setTargetArgument($input, $message);
            $this->checkTarget($input);
            return;
        }

        $sourceName = $input->getArgument(self::ARGUMENT_SOURCE);
        $source = $this->environmentService->getAliasByName($sourceName);

        if ($source === $target) {
            $message = 'Target and source are the same environment, it\'s aligned ;-)';
            $this->setTargetArgument($input, $message);
            $this->checkTarget($input);
            return;
        }

        $this->io->note(\sprintf('Continuing with the target environment "%s"', $targetName));
    }

    private function setTargetArgument(InputInterface $input, string $message)
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $targetName = $this->io->choice('Select an existing (not snapshot) environment as target', $this->environmentService->getNotSnapshotEnvironmentsNames());
        $input->setArgument(self::ARGUMENT_TARGET, $targetName);
    }
}
