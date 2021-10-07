<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Delete;

use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\IndexService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DeleteOrphanIndexesCommand extends AbstractCommand
{
    private IndexService $indexService;

    protected static $defaultName = Commands::DELETE_ORPHANS;

    public function __construct(IndexService $indexService)
    {
        parent::__construct();
        $this->indexService = $indexService;
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
