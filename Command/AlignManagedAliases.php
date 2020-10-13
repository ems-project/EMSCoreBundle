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
            ->setDescription('Align a managed alias to another')
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

        $sourceName = $input->getArgument('source');
        $targetName = $input->getArgument('target');
        if (!is_string($targetName)) {
            throw new \RuntimeException('Target name as to be a string');
        }
        if (!is_string($sourceName)) {
            throw new \RuntimeException('Source name as to be a string');
        }

        $this->aliasService->build();
        $source = $this->aliasService->getManagedAliasByName($sourceName);
        $target = $this->aliasService->getManagedAliasByName($targetName);

        $actions = [
            'add' => [],
            'remove' => [],
        ];
        foreach ($source->getIndexes() as $index) {
            if (!isset($target->getIndexes()[$index['name']])) {
                $actions['add'][] = $index['name'];
            }
        }
        foreach ($target->getIndexes() as $index) {
            if (!isset($source->getIndexes()[$index['name']])) {
                $actions['remove'][] = $index['name'];
            }
        }

        if (empty($actions['add']) && empty($actions['remove'])) {
            $output->writeln(sprintf("The alias %s was already aligned to the alias %s", $targetName, $sourceName));
            return 0;
        }
        $this->aliasService->updateAlias($target->getAlias(), $actions);
        $output->writeln(sprintf("The alias %s has been aligned to the alias %s", $targetName, $sourceName));
    }
}
