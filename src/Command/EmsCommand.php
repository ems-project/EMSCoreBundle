<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class EmsCommand extends ContainerAwareCommand
{
    /** @var Client */
    protected $client;
    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger, Client $client)
    {
        $this->logger = $logger;
        $this->client = $client;

        parent::__construct();
    }

    protected function formatStyles(OutputInterface &$output): void
    {
        $output->getFormatter()->setStyle('error', new OutputFormatterStyle('red', 'yellow', ['bold']));
        $output->getFormatter()->setStyle('comment', new OutputFormatterStyle('yellow', null, ['bold']));
        $output->getFormatter()->setStyle('notice', new OutputFormatterStyle('blue', null));
    }
}
