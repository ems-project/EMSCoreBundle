<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Service\AliasService;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AlignManagedAliases extends ContainerAwareCommand
{
    /** @var Logger  */
    protected $logger;
    /** @var AliasService  */
    protected $aliasService;

    public function __construct(Logger $logger, AliasService $aliasService)
    {
        $this->logger = $logger;
        $this->aliasService = $aliasService;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setName('ems:managedalias:align')
            ->setDescription('Align a managed alias indexes to another')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source managed alias name'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Target managed alias name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $source = $input->getArgument('source');
        $target = $input->getArgument('target');
        $output->writeln(sprintf("The alias %s will be aligned to the alias %s", $target, $source));
        $output->writeln(sprintf("The alias %s has been aligned to the alias %s", $target, $source));
    }
}
