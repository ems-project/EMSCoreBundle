<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class EmsCommand extends ContainerAwareCommand
{
	/**@var Client*/
	protected  $client;
	/**@var Logger*/
	protected $logger;
	/**@var Session*/
	protected $session;
	
	public function __construct(Logger $logger, Client $client, Session $session) {
		$this->logger = $logger;
		$this->client = $client;
		$this->session = $session;
		
		parent::__construct();
	}
	
	protected function configure() {
        $this
            ->setName('ems:waitforgreen')
            ->setDescription('Wait that the elasticsearch cluster is back to green');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
    	$this->waitForGreen($output);
    }
    
    protected function formatFlash(OutputInterface &$output){
    	$output->getFormatter()->setStyle('error', new OutputFormatterStyle('red', 'yellow', array('bold')));
    	$output->getFormatter()->setStyle('comment', new OutputFormatterStyle('yellow', null, array('bold')));
    	$output->getFormatter()->setStyle('notice', new OutputFormatterStyle('blue', null));
    }
    	
    protected function flushFlash(OutputInterface $output, $contextMsg=null){
    	if($this->session->isStarted()) {
	    	foreach($this->session->getFlashBag()->get('error') as $error){
	    		$output->writeln('<error>'.($contextMsg?$contextMsg.': ':'').$error.'</error>');
	    	}
	    	foreach($this->session->getFlashBag()->get('warning') as $warning){
	    		$output->writeln('<comment>'.($contextMsg?$contextMsg.': ':'').$warning.'</comment>');
	    	}
	    	foreach($this->session->getFlashBag()->get('notice') as $notice){
	    		$output->writeln('<notice>'.($contextMsg?$contextMsg.': ':'').$notice.'</notice>');
	    	}
	    	$this->session->save();
    	}
    }
    

    protected function waitForGreen(OutputInterface $output){
    	$output->write('Waiting for green...');
    	$this->client->cluster()->health(['wait_for_status' => 'green']);
    	$output->writeln(' Green!');
    }
}