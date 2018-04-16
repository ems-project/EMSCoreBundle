<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Service\EnvironmentService;

class EnvironmentCommand extends ContainerAwareCommand
{

	/**@var Logger*/
	private $logger;
	/**@var EnvironmentService*/
	private $environmentService;

	public function __construct(Logger $logger, EnvironmentService $environmentService )
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
        if($input->hasOption('all')){
            $environments = $this->environmentService->getAll();
        }
        else {
            $environments = $this->environmentService->getManagedEnvironement();
        }

        /** @var Environment $environment */
        foreach ( $environments as $environment) {
            $output->writeln($environment->getName());
        }
    }


}
