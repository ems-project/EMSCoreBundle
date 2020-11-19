<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Service\IndexService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOrphanIndexesCommand extends EmsCommand
{
    /** @var LoggerInterface */
    protected $logger;
    /** @var IndexService */
    protected $indexService;

    protected static $defaultName = 'ems:delete:orphans';

    public function __construct(LoggerInterface $logger, Client $client, IndexService $indexService)
    {
        $this->logger = $logger;
        $this->indexService = $indexService;
        parent::__construct($logger, $client);
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
