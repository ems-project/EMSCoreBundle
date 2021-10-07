<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class EnvironmentListCommand extends AbstractCommand
{
    private EnvironmentService $environmentService;

    protected static $defaultName = Commands::ENVIRONMENT_LIST;

    public function __construct(EnvironmentService $environmentService)
    {
        parent::__construct();
        $this->environmentService = $environmentService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List the environments defined')
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'List all environments (by default list internal environments only)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasOption('all')) {
            $environments = $this->environmentService->getEnvironments();
        } else {
            $environments = $this->environmentService->getManagedEnvironement();
        }

        foreach ($environments as $environment) {
            $output->writeln($environment->getName());
        }

        return 0;
    }
}
