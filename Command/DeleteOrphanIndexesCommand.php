<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Service\AliasService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Elasticsearch\Common\Exceptions\Missing404Exception;

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
            $this->deleteOrphanIndex($index, $output);
        }
    }

    private function deleteOrphanIndex($index, OutputInterface $output)
    {
        try {
            $this->client->indices()->delete([
                'index' => $index['name'],
            ]);
            $output->writeln('The index with name ' . $index['name'] . ' has been deleted.');
        } catch (Missing404Exception $e) {
            $output->writeln('The index with name ' . $index['name'] . ' was not found and could not be deleted. Continuing cleaning...');
        }
    }
}
