<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Service\IndexService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOrphanIndexesCommand extends AbstractCommand
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
        try {
            $this->indexService->deleteOrphanIndexes();

            return self::EXECUTE_SUCCESS;
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());

            return self::EXECUTE_ERROR;
        }
    }
}
