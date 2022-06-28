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
    private string $user = 'SYSTEM_ALIGN';

    public const ARGUMENT_SOURCE = 'source';
    public const ARGUMENT_TARGET = 'target';
    public const OPTION_FORCE = 'force';

    public const OPTION_SNAPSHOT = 'snapshot';
    public const OPTION_USER = 'user';

    protected static $defaultName = Commands::ENVIRONMENT_ALIGN;

    protected function configure(): void
    {
        $this
            ->setDescription('Align an environment from another one')
            ->addArgument(self::ARGUMENT_SOURCE, InputArgument::REQUIRED, 'Environment source name')
            ->addArgument(self::ARGUMENT_TARGET, InputArgument::REQUIRED, 'Environment target name')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'If set, the task will be performed (protection)')
            ->addOption(self::OPTION_SNAPSHOT, null, InputOption::VALUE_NONE, 'If set, the target environment will be tagged as a snapshot after the alignment')
            ->addOption(self::OPTION_USER, null, InputOption::VALUE_REQUIRED, 'Lock user', $this->user)
        ;

        $this->configureRevisionSearcher();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Environment - Align');

        $this->initializeRevisionSearcher();

        $this->user = $this->getOptionString(self::OPTION_USER, $this->user);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->source = $this->choiceEnvironment(self::ARGUMENT_SOURCE, 'Select an existing environment as source');
        $this->target = $this->choiceEnvironment(self::ARGUMENT_TARGET, 'Select an existing environment as target');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
            $snapShot = $this->environmentService->findByName($this->target->getName());
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
