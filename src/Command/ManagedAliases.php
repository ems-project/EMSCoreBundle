<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Service\AliasService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ManagedAliases extends Command
{
    protected static $defaultName = 'ems:managedalias:list';

    public function __construct(protected LoggerInterface $logger, protected AliasService $aliasService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('List managed aliases')
            ->addOption('detailed', null, InputOption::VALUE_NONE, 'List all indexes in each managed alias');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->aliasService->build();
        $detailed = $input->getOption('detailed');
        /** @var ManagedAlias $alias */
        foreach ($this->aliasService->getManagedAliases() as $alias) {
            $output->writeln($alias->getName());
            if ($detailed) {
                foreach ($alias->getIndexes() as $index) {
                    $output->writeln(\sprintf(' - Index: %s (%d)', $index['name'], $index['count']));
                }
            }
        }

        return 0;
    }
}
