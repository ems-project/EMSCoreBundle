<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Service\ElasticaService;
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
    /** @var string */
    protected static $defaultName = 'ems:environment:align';
    /** @var Registry */
    protected $doctrine;
    /** @var LoggerInterface */
    private $logger;
    /** @var DataService */
    protected $data;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var PublishService */
    private $publishService;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var SymfonyStyle */
    private $io;
    /** @var int */
    private $scrollSize;
    /** @var string */
    private $scrollTimeout;
    /** @var string */
    private $searchQuery;
    /** @var string */
    public const ARGUMENT_SOURCE = 'source';
    /** @var string */
    public const ARGUMENT_TARGET = 'target';
    /** @var string */
    public const ARGUMENT_SCROLL_SIZE = 'scrollSize';
    /** @var string */
    public const ARGUMENT_SCROLL_TIMEOUT = 'scrollTimeout';
    /** @var string */
    public const OPTION_FORCE = 'force';
    /** @var string */
    public const OPTION_SEARCH_QUERY = 'searchQuery';
    /** @var string */
    public const OPTION_SNAPSHOT = 'snapshot';
    /** @var string */
    public const OPTION_STRICT = 'strict';
    /** @var string */
    public const DEFAULT_SCROLL_SIZE = '100';
    /** @var string */
    public const DEFAULT_SCROLL_TIMEOUT = '1m';
    /** @var string */
    public const DEFAULT_SEARCH_QUERY = '{}';

    public function __construct(Registry $doctrine, LoggerInterface $logger, ElasticaService $elasticaService, DataService $data, ContentTypeService $contentTypeService, EnvironmentService $environmentService, PublishService $publishService)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->elasticaService = $elasticaService;
        $this->data = $data;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->publishService = $publishService;

        parent::__construct();
    }

    protected function configure(): void
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

        $scrollSize = \intval($input->getArgument(self::ARGUMENT_SCROLL_SIZE));
        if (0 === $scrollSize) {
            throw new \RuntimeException('Unexpected scroll size argument');
        }
        $this->scrollSize = $scrollSize;
        $scrollTimeout = $input->getArgument(self::ARGUMENT_SCROLL_TIMEOUT);
        if (!\is_string($scrollTimeout)) {
            throw new \RuntimeException('Unexpected scroll timeout argument');
        }
        $this->scrollTimeout = $scrollTimeout;
        $searchQuery = $input->getOption(self::OPTION_SEARCH_QUERY);
        if (!\is_string($searchQuery)) {
            throw new \RuntimeException('Unexpected query argument');
        }
        $this->searchQuery = $searchQuery;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
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
        if (!\is_string($targetName)) {
            throw new \RuntimeException('Target name as to be a string');
        }
        if (!\is_string($sourceName)) {
            throw new \RuntimeException('Source name as to be a string');
        }

        $this->environmentService->clearCache();
        $source = $this->environmentService->getAliasByName($sourceName);
        if (false === $source) {
            throw new \RuntimeException('Source environment not found');
        }
        $target = $this->environmentService->getAliasByName($targetName);
        if (false === $target) {
            throw new \RuntimeException('Target environment not found');
        }

        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $source->getAlias(),
            'size' => $this->scrollSize,
            'body' => $this->searchQuery,
        ]);

        $scroll = $this->elasticaService->scroll($search, $this->scrollTimeout);
        $total = $this->elasticaService->count($search);

        $this->io->note(\sprintf('The source environment contains %s elements, start aligning environments...', $total));

        $this->io->progressStart($total);

        $deletedRevision = 0;
        $alreadyAligned = 0;
        $targetIsPreviewEnvironment = [];

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                if (false === $result) {
                    continue;
                }
                $contentType = $this->contentTypeService->getByName($result->getSource()['_contenttype']);
                if (false === $contentType) {
                    throw new \RuntimeException('Unexpected null content type');
                }
                $revision = $this->data->getRevisionByEnvironment($result->getId(), $contentType, $source);
                if ($revision->getDeleted()) {
                    ++$deletedRevision;
                } elseif ($contentType->getEnvironment() === $target) {
                    if (!isset($targetIsPreviewEnvironment[$contentType->getName()])) {
                        $targetIsPreviewEnvironment[$contentType->getName()] = 0;
                    }
                    ++$targetIsPreviewEnvironment[$contentType->getName()];
                } else {
                    if (0 == $this->publishService->publish($revision, $target, true)) {
                        ++$alreadyAligned;
                    }
                }
                $this->io->progressAdvance();
            }
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

    private function checkSource(InputInterface $input): void
    {
        $sourceName = $input->getArgument(self::ARGUMENT_SOURCE);
        if (null === $sourceName) {
            $message = 'Source environment not provided';
            $this->setSourceArgument($input, $message);

            return;
        }
        if (!\is_string($sourceName)) {
            throw new \RuntimeException('Source name as to be a string');
        }

        $source = $this->environmentService->getAliasByName($sourceName);
        if (false === $source) {
            $message = \sprintf('Source environment "%s" not found', $sourceName);
            $this->setSourceArgument($input, $message);
            $this->checkSource($input);

            return;
        }

        $this->io->note(\sprintf('Continuing with the source environment "%s"', $sourceName));
    }

    private function setSourceArgument(InputInterface $input, string $message): void
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $sourceName = $this->io->choice('Select an existing environment as source', $this->environmentService->getEnvironmentNames());
        $input->setArgument(self::ARGUMENT_SOURCE, $sourceName);
    }

    private function checkTarget(InputInterface $input): void
    {
        $targetName = $input->getArgument(self::ARGUMENT_TARGET);
        if (null === $targetName) {
            $message = 'Target environment not provided';
            $this->setTargetArgument($input, $message);

            return;
        }
        if (!\is_string($targetName)) {
            throw new \RuntimeException('Target name as to be a string');
        }

        $this->environmentService->clearCache();
        $target = $this->environmentService->getByName($targetName);
        if (false === $target) {
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
        if (!\is_string($sourceName)) {
            throw new \RuntimeException('Source name as to be a string');
        }
        $source = $this->environmentService->getAliasByName($sourceName);

        if ($source === $target) {
            $message = 'Target and source are the same environment, it\'s aligned ;-)';
            $this->setTargetArgument($input, $message);
            $this->checkTarget($input);

            return;
        }

        $this->io->note(\sprintf('Continuing with the target environment "%s"', $targetName));
    }

    private function setTargetArgument(InputInterface $input, string $message): void
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
