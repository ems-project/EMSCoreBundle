<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ManagedAlias;

use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Service\AliasService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ManagedAliasListCommand extends AbstractCommand
{
    private AliasService $aliasService;

    protected static $defaultName = Commands::MANAGED_ALIAS_LIST;

    public function __construct(AliasService $aliasService)
    {
        parent::__construct();
        $this->aliasService = $aliasService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List managed aliases')
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
