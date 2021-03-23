<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Check;

use EMS\CoreBundle\Command\RebuildCommand;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class AliasesCheckCommand extends Command
{
    public const COMMAND = 'ems:check:aliases';
    private EnvironmentService $environmentService;
    private AliasService $aliasService;
    private JobService $jobService;
    private SymfonyStyle $io;

    public function __construct(EnvironmentService $environmentService, AliasService $aliasService, JobService $jobService)
    {
        $this->environmentService = $environmentService;
        $this->aliasService = $aliasService;
        $this->jobService = $jobService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND)
            ->setDescription('Checks that all managed environments have their corresponding alias and index present in the cluster. If not and if they are no pending job a rebuild job is queued.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Start checking environment\'s aliase');
        foreach ($this->environmentService->getEnvironments() as $environment) {
            if (!$environment->getManaged()) {
                continue;
            }
            if ($environment->hasAlias() && $this->aliasService->hasAliasInCluster($environment->getAlias())) {
                $this->io->writeln(\sprintf('Environment\'s alias %s is present', $environment->getName()));
                continue;
            }
            $this->io->warning(\sprintf('The %s environment\'s alias is missing', $environment->getName()));

            if ($this->jobService->countPending() > 0) {
                $this->io->warning('The job\'s queue is not empty');
                break;
            }

            $fakeUser = new User();
            $fakeUser->setUsername(self::COMMAND);
            $command = \join(' ', [
                RebuildCommand::COMMAND,
                $environment->getName(),
            ]);
            $this->jobService->createCommand($fakeUser, $command);
            $this->io->writeln(\sprintf('A command `%s` has been initialized', $command));
            break;
        }

        return 0;
    }
}
