<?php

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
    /** @var Client */
    protected $client;

    protected static $defaultName = 'ems:delete:orphans';
    /**
     * @var IndexService
     */
    private $indexService;

    public function __construct(LoggerInterface $logger, Client $client, IndexService $indexService)
    {
        $this->logger = $logger;
        $this->indexService = $indexService;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this->setDescription('Removes all orphan indexes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->indexService->deleteOrphanIndexes();
    }
}
