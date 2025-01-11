<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::ENVIRONMENT_ALIGN,
    description: 'Align an environment from another one.',
    hidden: false
)]
class AlignCommand extends AbstractEnvironmentCommand
{
    private Environment $source;
    private Environment $target;
    private bool $publicationTemplate = false;

    final public const string ARGUMENT_SOURCE = 'source';
    final public const string ARGUMENT_TARGET = 'target';
    final public const string OPTION_SNAPSHOT = 'snapshot';
    final public const string OPTION_PUBLICATION_TEMPLATE = 'publication-template';

    private const string LOCK_USER = 'SYSTEM_ALIGN';

    /** @var array<string, int> */
    private array $counters = [
        'published' => 0,
        'deleted' => 0,
        'aligned' => 0,
        'publication_template' => 0,
    ];

    /** @var array<int, string> */
    private array $bulkResultCounter = [
        0 => 'aligned',
        1 => 'published',
        -1 => 'publication_template',
    ];

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_SOURCE, InputArgument::REQUIRED, 'Environment source name')
            ->addArgument(self::ARGUMENT_TARGET, InputArgument::REQUIRED, 'Environment target name')
            ->addOption(self::OPTION_SNAPSHOT, null, InputOption::VALUE_NONE, 'If set, the target environment will be tagged as a snapshot after the alignment')
            ->addOption(self::OPTION_PUBLICATION_TEMPLATE, null, InputOption::VALUE_NONE, 'If set, the environment publication template will be used')
        ;

        $this->configureForceProtection();
        $this->configureRevisionSearcher();
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Environment - Align');

        $this->initializeRevisionSearcher(self::LOCK_USER);
        $this->publicationTemplate = $this->getOptionBool(self::OPTION_PUBLICATION_TEMPLATE);
        $this->publishService->bulkStart($this->revisionSearcher->getSize(), $this->logger);
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->source = $this->choiceEnvironment(self::ARGUMENT_SOURCE, 'Select an existing environment as source');
        $this->target = $this->choiceEnvironment(self::ARGUMENT_TARGET, 'Select an existing environment as target');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->forceProtection($input)) {
            return self::EXECUTE_ERROR;
        }

        $search = $this->revisionSearcher->create($this->source, $this->searchQuery);
        $this->io->section(\sprintf('The source environment contains %s elements, start aligning environments...', $search->getTotal()));

        if ($this->dryRun) {
            $this->io->success('Dry run finished');

            return self::EXECUTE_SUCCESS;
        }

        $targetIsPreviewEnvironment = [];
        $ouuids = [];

        $this->io->progressStart($search->getTotal());
        foreach ($this->revisionSearcher->search($this->source, $search) as $revisions) {
            $this->revisionSearcher->lock($revisions, $this->lockUser);

            foreach ($revisions->transaction() as $revision) {
                $contentType = $revision->giveContentType();
                $ouuids[] = $revision->giveOuuid();

                if ($revision->getDeleted()) {
                    ++$this->counters['deleted'];
                } elseif ($contentType->giveEnvironment()->getName() === $this->target->getName()) {
                    if (!isset($targetIsPreviewEnvironment[$contentType->getName()])) {
                        $targetIsPreviewEnvironment[$contentType->getName()] = 0;
                    }
                    ++$targetIsPreviewEnvironment[$contentType->getName()];
                } else {
                    $bulkResult = $this->publishService->bulkPublish($revision, $this->target, $this->lockUser, $this->publicationTemplate);
                    ++$this->counters[$this->bulkResultCounter[$bulkResult]];
                }

                $this->io->progressAdvance();
            }

            $this->revisionSearcher->unlock($revisions);
        }
        $this->publishService->bulkFinished();
        $this->io->progressFinish();

        $this->unpublishNotAligned(...$ouuids);

        $this->environmentService->clearCache();

        if ($input->getOption(self::OPTION_SNAPSHOT)) {
            $snapShot = $this->environmentService->giveByName($this->target->getName());
            $this->environmentService->setSnapshotTag($snapShot);
            $this->io->note(\sprintf('The target environment "%s" was tagged as a snapshot', $snapShot->getName()));
        }

        if ($this->counters['deleted'] > 0) {
            $this->io->caution(\sprintf('%s deleted revisions were not aligned', $this->counters['deleted']));
        }
        if ($this->counters['aligned'] > 0) {
            $this->io->note(\sprintf('%s revisions were already aligned', $this->counters['aligned']));
        }
        if ($this->counters['publication_template'] > 0) {
            $publisher = $this->publishService->getEnvironmentPublisher($this->target);
            $publicationWarnings = $publisher->getAllWarningMessages();
            $publicationErrors = $publisher->getAllErrorMessages();

            if (\count($publicationWarnings) > 0) {
                $this->io->warning($publicationWarnings);
            }
            if (\count($publicationErrors) > 0) {
                $this->io->error($publicationErrors);
            }
        }

        foreach ($targetIsPreviewEnvironment as $ctName => $counter) {
            $this->io->caution(\sprintf(
                '%d %s revisions were not aligned as %s is the default environment',
                $counter, $ctName, $this->target->getName()
            ));
        }

        if ($this->counters['published'] > 0) {
            $this->io->info(\sprintf('%s revisions were published', $this->counters['published']));
        }

        $this->io->success(\vsprintf('Environments %s -> %s were aligned.', [
            $this->source->getName(),
            $this->target->getName(),
        ]));

        return self::EXECUTE_SUCCESS;
    }

    private function unpublishNotAligned(string ...$ouuids): void
    {
        $this->io->section(\sprintf('Unpublish not aligned documents from "%s"', $this->target->getName()));
        $countUnpublished = 0;

        $targetSearch = $this->revisionSearcher->create($this->target, $this->searchQuery);
        $this->io->progressStart($targetSearch->getTotal());

        foreach ($this->revisionSearcher->search($this->target, $targetSearch) as $revisions) {
            $this->revisionSearcher->lock($revisions, $this->lockUser);

            foreach ($revisions->transaction() as $revision) {
                $this->io->progressAdvance();

                if (\in_array($revision->getOuuid(), $ouuids, true)) {
                    continue;
                }

                if ($revision->giveContentType()->giveEnvironment() === $this->target) {
                    $this->io->warning(\sprintf('Could not unpublish the document %s, target is default environment', $revision->getEmsLink()));
                    continue;
                }

                $this->publishService->bulkUnpublish($revision, $this->target);
                ++$countUnpublished;
            }

            $this->revisionSearcher->unlock($revisions);
        }

        $this->publishService->bulkFinished();
        $this->io->progressFinish();

        $this->io->note(\sprintf('Unpublished %d documents from "%s"', $countUnpublished, $this->target->getName()));
    }
}
