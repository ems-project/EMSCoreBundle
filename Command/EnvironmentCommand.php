<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvironmentCommand extends ContainerAwareCommand
{

    /**@var LoggerInterface */
    private $logger;
    /**@var EnvironmentService*/
    private $environmentService;

    public function __construct(LoggerInterface $logger, EnvironmentService $environmentService)
    {
        $this->logger = $logger;
        $this->environmentService = $environmentService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:environment:list')
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

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $output->writeln($environment->getName());
        }
        return 0;
    }
}
