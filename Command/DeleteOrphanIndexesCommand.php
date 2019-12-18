<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\EnvironmentService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOrphanIndexesCommand extends EmsCommand
{
    /**@var EnvironmentService */
    private $environmentService;
    protected $aliasService;

    public function __construct(LoggerInterface $logger, Client $client, EnvironmentService $environmentService, AliasService $aliasService)
    {
        $this->logger = $logger;
        $this->client = $client;
        $this->environmentService = $environmentService;
        $this->aliasService = $aliasService;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this->logger->info('Configure the DeleteOrphanIndexesCommand');
        $this
            ->setName('ems:delete:orphans')
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
