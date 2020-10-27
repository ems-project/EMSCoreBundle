<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EmsCommand extends ContainerAwareCommand
{
    /** @var Client*/
    protected $client;
    /** @var LoggerInterface*/
    protected $logger;
    
    public function __construct(LoggerInterface $logger, Client $client)
    {
        $this->logger = $logger;
        $this->client = $client;
        
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this
            ->setName('ems:waitforgreen')
            ->setDescription('Wait that the elasticsearch cluster is back to green');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->waitForGreen($output);
        return 0;
    }
    
    protected function formatStyles(OutputInterface &$output): void
    {
        $output->getFormatter()->setStyle('error', new OutputFormatterStyle('red', 'yellow', array('bold')));
        $output->getFormatter()->setStyle('comment', new OutputFormatterStyle('yellow', null, array('bold')));
        $output->getFormatter()->setStyle('notice', new OutputFormatterStyle('blue', null));
    }
    

    protected function waitForGreen(OutputInterface $output): void
    {
        $output->write('Waiting for green...');
        $this->client->cluster()->health(['wait_for_status' => 'green']);
        $output->writeln(' Green!');
    }
}
