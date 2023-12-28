<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\IndexService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::DELETE_ORPHANS,
    description: 'Removes all orphan indexes.',
    hidden: false,
    aliases: ['ems:delete:orphans']
)]
class DeleteOrphanIndexesCommand extends EmsCommand
{
    public function __construct(protected IndexService $indexService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexService->deleteOrphanIndexes();

        return 0;
    }
}
