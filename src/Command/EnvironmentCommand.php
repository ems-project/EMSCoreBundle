<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::ENVIRONMENT_LIST,
    description: 'List the environments defined.',
    hidden: false,
    aliases: ['ems:environment:list']
)]
class EnvironmentCommand extends Command
{
    public function __construct(private readonly EnvironmentService $environmentService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'List all environments (by default list internal environments only)'
            )
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasOption('all')) {
            $environments = $this->environmentService->getEnvironments();
        } else {
            $environments = $this->environmentService->getManagedEnvironement();
        }

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $output->writeln($environment->getName());
        }

        return 0;
    }
}
