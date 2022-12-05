<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvironmentCommand extends Command
{
    protected static $defaultName = 'ems:environment:list';

    public function __construct(private readonly EnvironmentService $environmentService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('List the environments defined')
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

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $output->writeln($environment->getName());
        }

        return 0;
    }
}
