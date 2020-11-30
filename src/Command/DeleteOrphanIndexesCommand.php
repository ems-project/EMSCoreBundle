<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Service\IndexService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOrphanIndexesCommand extends EmsCommand
{
    /** @var IndexService */
    protected $indexService;

    protected static $defaultName = 'ems:delete:orphans';

    public function __construct(IndexService $indexService)
    {
        $this->indexService = $indexService;
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
