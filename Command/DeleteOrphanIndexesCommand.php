<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Service\AliasService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOrphanIndexesCommand extends EmsCommand
{
    /** @var LoggerInterface */
    protected $logger;
    /** @var Client */
    protected $client;
    /** @var AliasService */
    protected $aliasService;

    public function __construct(LoggerInterface $logger, Client $client, AliasService $aliasService)
    {
        $this->logger = $logger;
        $this->client = $client;
        $this->aliasService = $aliasService;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this->setName('ems:delete:orphans')
            ->setDescription('Removes all orphan indexes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->aliasService->build();
        foreach ($this->aliasService->getOrphanIndexes() as $index) {
            $this->client->indices()->delete([
                'index' => $index['name'],
            ]);
            $this->logger->notice('log.index.delete_orphan_index', [
                'index_name' => $index['name'],
            ]);
        }
    }
}
