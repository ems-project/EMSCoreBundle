<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AlignCommand extends AbstractEnvironmentCommand
{
    private Environment $source;
    private Environment $target;

    public const ARGUMENT_SOURCE = 'source';
    public const ARGUMENT_TARGET = 'target';
    public const OPTION_SNAPSHOT = 'snapshot';

    private const LOCK_USER = 'SYSTEM_ALIGN';

    protected static $defaultName = Commands::ENVIRONMENT_ALIGN;

    protected function configure(): void
    {
        $this
            ->setDescription('Align an environment from another one')
            ->addArgument(self::ARGUMENT_SOURCE, InputArgument::REQUIRED, 'Environment source name')
            ->addArgument(self::ARGUMENT_TARGET, InputArgument::REQUIRED, 'Environment target name')
            ->addOption(self::OPTION_SNAPSHOT, null, InputOption::VALUE_NONE, 'If set, the target environment will be tagged as a snapshot after the alignment')
        ;

        $this->configureForceProtection();
        $this->configureRevisionSearcher();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Environment - Align');

        $this->initializeRevisionSearcher(self::LOCK_USER);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->source = $this->choiceEnvironment(self::ARGUMENT_SOURCE, 'Select an existing environment as source');
        $this->target = $this->choiceEnvironment(self::ARGUMENT_TARGET, 'Select an existing environment as target');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->forceProtection($input)) {
            return self::EXECUTE_ERROR;
        }

        $search = $this->revisionSearcher->create($this->source, $this->searchQuery);
        $bulkSize = $this->revisionSearcher->getSize();

        $this->io->note(\sprintf('The source environment contains %s elements, start aligning environments...', $search->getTotal()));

        if ($this->dryRun) {
            $this->io->success('Dry run finished');

            return self::EXECUTE_SUCCESS;
        }

        $deletedRevision = 0;
        $alreadyAligned = 0;
        $targetIsPreviewEnvironment = [];

        $this->io->progressStart($search->getTotal());
        foreach ($this->revisionSearcher->search($this->source, $search) as $revisions) {
            $this->revisionSearcher->lock($revisions, $this->lockUser);
            $this->publishService->bulkStart($bulkSize, $this->logger);

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
                    if (0 == $this->publishService->bulkPublish($revision, $this->target, $this->lockUser)) {
                        ++$alreadyAligned;
                    }
                }

                $this->io->progressAdvance();
            }

            $this->publishService->bulkFinished();
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
