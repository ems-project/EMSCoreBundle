<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Service\AliasService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: Commands::MANAGED_ALIAS_LIST, description: 'List managed aliases.', aliases: ['ems:managedalias:list'], hidden: false)]
class ManagedAliases extends AbstractCoreCommand
{
    public function __construct(protected LoggerInterface $logger, protected AliasService $aliasService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setDescription('')
            ->addOption('detailed', null, InputOption::VALUE_NONE, 'List all indexes in each managed alias');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->aliasService->build();
        $detailed = $input->getOption('detailed');
        /** @var ManagedAlias $alias */
        foreach ($this->aliasService->getManagedAliases() as $alias) {
            $this->io->text($alias->getName());
            if ($detailed) {
                foreach ($alias->getIndexes() as $index) {
                    $this->io->text(\sprintf(' - Index: %s (%d)', $index['name'], $index['count']));
                }
            }
        }

        return 0;
    }
}
