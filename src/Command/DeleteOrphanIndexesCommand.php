<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Service\IndexService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOrphanIndexesCommand extends EmsCommand
{
    protected static $defaultName = 'ems:delete:orphans';

    public function __construct(protected IndexService $indexService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Removes all orphan indexes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexService->deleteOrphanIndexes();

        return 0;
    }
}
