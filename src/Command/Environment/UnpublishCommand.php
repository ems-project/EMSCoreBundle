<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::ENVIRONMENT_UNPUBLISH,
    description: 'Unpublish revision from an environment.',
    hidden: false
)]
final class UnpublishCommand extends AbstractEnvironmentCommand
{
    private Environment $environment;
    private int $counter = 0;
    /** @var array<string, int> */
    private array $warnings = [];

    public const string ARGUMENT_ENVIRONMENT = 'environment';

    private const string LOCK_USER = 'SYSTEM_UNPUBLISH';

    public function __construct(
        RevisionSearcher $revisionSearcher,
        EnvironmentService $environmentService,
        PublishService $publishService,
    ) {
        parent::__construct($revisionSearcher, $environmentService, $publishService, self::LOCK_USER);
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument(self::ARGUMENT_ENVIRONMENT, InputArgument::REQUIRED, 'Environment name')
        ;

        $this->configureForceProtection();
        $this->configureRevisionSearcher(self::LOCK_USER);
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Environment - Unpublish');

        $this->initializeRevisionSearcher();
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->environment = $this->choiceEnvironment(self::ARGUMENT_ENVIRONMENT, 'Select an existing environment');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->forceProtection($input)) {
            return self::EXECUTE_ERROR;
        }

        $search = $this->revisionSearcher->create($this->environment, $this->searchQuery);
        $bulkSize = $this->revisionSearcher->getSize();

        $this->io->note(\sprintf('Found "%d" revisions in "%s" environment', $search->getTotal(), $this->environment));

        if ($this->dryRun) {
            $this->io->success('Dry run finished');

            return self::EXECUTE_SUCCESS;
        }

        $this->io->progressStart($search->getTotal());
        foreach ($this->revisionSearcher->search($this->environment, $search) as $revisions) {
            $this->revisionSearcher->lock($revisions, ${$this}->getUsername());
            $this->publishService->bulkStart($bulkSize, $this->logger);

            foreach ($revisions->transaction() as $revision) {
                $this->io->progressAdvance();

                try {
                    $this->publishService->bulkUnpublish($revision, $this->environment, $this->getUsername());
                    ++$this->counter;
                } catch (\LogicException $e) {
                    $this->warnings[$e->getMessage()] = ($this->warnings[$e->getMessage()] ?? 0) + 1;
                }
            }

            $this->publishService->bulkFinished();
            $this->revisionSearcher->unlock($revisions);
        }
        $this->io->progressFinish();

        foreach ($this->warnings as $warning => $warningCounter) {
            $this->io->warning(\sprintf('%s : %d', $warning, $warningCounter));
        }

        $this->io->success(\sprintf('Unpublished "%d" documents from "%s"', $this->counter, $this->environment));

        return self::EXECUTE_SUCCESS;
    }
}
