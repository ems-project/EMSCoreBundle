<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Check;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: Commands::MANAGED_ALIAS_CHECK,
    description: 'Checks that all managed environments have their corresponding alias and index present in the cluster.',
    hidden: false,
    aliases: ['ems:check:aliases']
)]
final class AliasesCheckCommand extends Command
{
    private const string OPTION_REPAIR = 'repair';
    private SymfonyStyle $io;
    private bool $repair = false;

    public function __construct(private readonly EnvironmentService $environmentService, private readonly AliasService $aliasService, private readonly JobService $jobService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(self::OPTION_REPAIR, null, InputOption::VALUE_NONE, 'If an environment does not have its alias present and if they are no pending job a rebuild job is queued.');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->repair = true === $input->getOption(self::OPTION_REPAIR);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Start checking environment\'s aliase');
        foreach ($this->environmentService->getEnvironments() as $environment) {
            if (!$environment->getManaged()) {
                continue;
            }
            if ($this->aliasService->hasAliasInCluster($environment->getAlias())) {
                $this->io->writeln(\sprintf('Environment\'s alias %s is present', $environment->getName()));
                continue;
            }
            $this->io->warning(\sprintf('The %s environment\'s alias is missing', $environment->getName()));

            if (!$this->repair) {
                continue;
            }

            if ($this->jobService->countPending() > 0) {
                $this->io->warning('The job\'s queue is not empty');
                break;
            }

            $fakeUser = new User();
            $fakeUser->setUsername(Commands::MANAGED_ALIAS_CHECK);
            $command = \implode(' ', [
                Commands::ENVIRONMENT_REBUILD,
                $environment->getName(),
            ]);
            $this->jobService->createCommand($fakeUser, $command);
            $this->io->writeln(\sprintf('A command `%s` has been initialized', $command));
            break;
        }

        return 0;
    }
}
