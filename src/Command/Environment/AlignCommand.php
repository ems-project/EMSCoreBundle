<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AlignCommand extends AbstractCommand
{
    private RevisionSearcher $revisionSearcher;
    private LoggerInterface $logger;
    private EnvironmentService $environmentService;
    private PublishService $publishService;

    private Environment $source;
    private Environment $target;
    private string $user = 'SYSTEM_ALIGN';

    private string $searchQuery;

    public const ARGUMENT_SOURCE = 'source';
    public const ARGUMENT_TARGET = 'target';
    public const OPTION_SCROLL_SIZE = 'scroll-size';
    public const OPTION_SCROLL_TIMEOUT = 'scroll-timeout';
    public const OPTION_FORCE = 'force';
    public const OPTION_SEARCH_QUERY = 'search-query';
    public const OPTION_SNAPSHOT = 'snapshot';
    public const OPTION_USER = 'user';

    protected static $defaultName = Commands::ENVIRONMENT_ALIGN;

    public function __construct(
        RevisionSearcher $revisionSearcher,
        LoggerInterface $logger,
        EnvironmentService $environmentService,
        PublishService $publishService
    ) {
        parent::__construct();
        $this->revisionSearcher = $revisionSearcher;
        $this->logger = $logger;
        $this->environmentService = $environmentService;
        $this->publishService = $publishService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Align an environment from another one')
            ->addArgument(self::ARGUMENT_SOURCE, InputArgument::REQUIRED, 'Environment source name')
            ->addArgument(self::ARGUMENT_TARGET, InputArgument::REQUIRED, 'Environment target name')
            ->addOption(self::OPTION_SCROLL_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request')
            ->addOption(self::OPTION_SCROLL_TIMEOUT, null, InputOption::VALUE_REQUIRED, 'Time to migrate "scrollSize" items i.e. 30s or 2m')
            ->addOption(self::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, 'Query used to find elasticsearch records to import', '{}')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'If set, the task will be performed (protection)')
            ->addOption(self::OPTION_SNAPSHOT, null, InputOption::VALUE_NONE, 'If set, the target environment will be tagged as a snapshot after the alignment')
            ->addOption(self::OPTION_USER, null, InputOption::VALUE_REQUIRED, 'Lock user', $this->user)
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Environment - Align');

        if ($scrollSize = $this->getOptionIntNull(self::OPTION_SCROLL_SIZE)) {
            $this->revisionSearcher->setSize($scrollSize);
        }
        if ($scrollTimeout = $this->getOptionStringNull(self::OPTION_SCROLL_TIMEOUT)) {
            $this->revisionSearcher->setTimeout($scrollTimeout);
        }

        $this->user = $this->getOptionString(self::OPTION_USER, $this->user);
        $this->searchQuery = $this->getOptionString(self::OPTION_SEARCH_QUERY);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->logger->info('Interact with AlignCommand');

        $environmentNames = $this->environmentService->getEnvironmentNames();

        $this->choiceArgumentString(self::ARGUMENT_SOURCE, 'Select an existing environment as source', $environmentNames);
        $this->choiceArgumentString(self::ARGUMENT_TARGET, 'Select an existing environment as target', $environmentNames);

        $this->source = $this->environmentService->giveByName($this->getArgumentString(self::ARGUMENT_SOURCE));
        $this->target = $this->environmentService->giveByName($this->getArgumentString(self::ARGUMENT_TARGET));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Execute');

        if (!$input->getOption(self::OPTION_FORCE)) {
            $this->io->error('Has protection, the force option is mandatory.');

            return self::EXECUTE_ERROR;
        }

        $search = $this->revisionSearcher->create($this->source, $this->searchQuery);
        $bulkSize = $this->revisionSearcher->getSize();

        $this->io->note(\sprintf('The source environment contains %s elements, start aligning environments...', $search->getTotal()));

        $deletedRevision = 0;
        $alreadyAligned = 0;
        $targetIsPreviewEnvironment = [];

        $this->io->progressStart($search->getTotal());
        foreach ($this->revisionSearcher->search($this->source, $search) as $revisions) {
            $this->revisionSearcher->lock($revisions, $this->user);
            $this->publishService->bulkPublishStart($bulkSize);

            foreach ($revisions->transaction() as $revision) {
                $contentType = $revision->giveContentType();

                if ($revision->getDeleted()) {
                    ++$deletedRevision;
                } elseif ($contentType->giveEnvironment()->getName() === $this->target->getName()) {
                    if (!isset($targetIsPreviewEnvironment[$contentType->getName()])) {
                        $targetIsPreviewEnvironment[$contentType->getName()] = 0;
                    }
                    ++$targetIsPreviewEnvironment[$contentType->getName()];
                } else {
                    if (0 == $this->publishService->bulkPublish($revision, $this->target)) {
                        ++$alreadyAligned;
                    }
                }

                $this->io->progressAdvance();
            }

            $this->publishService->bulkPublishFinished();
            $this->revisionSearcher->unlock($revisions);
        }
        $this->io->progressFinish();

        if ($input->getOption(self::OPTION_SNAPSHOT)) {
            $snapShot = $this->environmentService->giveByName($this->target->getName());
            $this->environmentService->setSnapshotTag($snapShot);
            $this->io->note(\sprintf('The target environment "%s" was tagged as a snapshot', $snapShot->getName()));
        }

        if ($deletedRevision > 0) {
            $this->io->caution(\sprintf('%s deleted revisions were not aligned', $deletedRevision));
        }

        if ($alreadyAligned > 0) {
            $this->io->note(\sprintf('%s revisions were already aligned', $alreadyAligned));
        }

        foreach ($targetIsPreviewEnvironment as $ctName => $counter) {
            $this->io->caution(\sprintf(
                '%s %s revisions were not aligned as %s is the default environment',
                $counter, $ctName, $this->target->getName()
            ));
        }

        $this->io->success(\vsprintf('Environments %s -> %s were aligned.', [
            $this->source->getName(),
            $this->target->getName(),
        ]));

        return self::EXECUTE_SUCCESS;
    }
}
