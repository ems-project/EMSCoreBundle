<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
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

    protected function configure()
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

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasOption('all')) {
            $environments = $this->environmentService->getAll();
        } else {
            $environments = $this->environmentService->getManagedEnvironement();
        }

        /** @var Environment $environment */
        foreach ($environments as $environment) {
            $output->writeln($environment->getName());
        }
    }
}
