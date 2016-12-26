<?php

// src/Ems/CoreBundle/Command/GreetCommand.php
namespace Ems\CoreBundle\Command;

use Ems\CoreBundle\Entity\Environment;
use Ems\CoreBundle\Repository\JobRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class ReindexCommand extends EmsCommand
{
	protected $client;
	protected $mapping;
	protected $doctrine;
	protected $logger;
	protected $container;
	private $instanceId;
	
	public function __construct(Registry $doctrine, Logger $logger, Client $client, $mapping, $container, $instanceId, Session $session)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
		$this->client = $client;
		$this->mapping = $mapping;
		$this->container = $container;
		$this->instanceId = $instanceId;
		parent::__construct($logger, $client, $session);
	}
	
    protected function configure()
    {
    	$this->logger->info('Configure the ReindexCommand');
        $this
            ->setName('ems:environment:reindex')
            ->setDescription('Reindex an environment in it\'s existing index')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Environment name'
            )
            ->addArgument(
                'index',
                InputArgument::OPTIONAL,
                'Elasticsearch index where to index environment objects'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {	
    	$this->logger->info('Execute the ReindexCommand');
    	$name = $input->getArgument('name');
    	/** @var EntityManager $em */
		$em = $this->doctrine->getManager();

		/** @var JobRepository $envRepo */
		$envRepo = $em->getRepository('Ems/CoreBundle:Environment');
		/** @var Environment $environment */
		$environment = $envRepo->findBy(['name' => $name, 'managed' => true]);
		if($environment && count($environment) == 1) {
			$environment = $environment[0];
			
			$index = $input->getArgument('index');
			if(!$index) {
				$index = $environment->getAlias();
			}
			
			$count = 0;
			$deleted = 0;
			$error = 0;
			
			// create a new progress bar
			$progress = new ProgressBar($output, count($environment->getRevisions()));
			// start and displays the progress bar
			$progress->start();
			
			/** @var \Ems\CoreBundle\Entity\Revision $revision */
			foreach ($environment->getRevisions() as $revision) {
				if($revision->getDeleted()){
					++$deleted;
					$this->session->getFlashBag()->add('warning', 'The revision '.$revision->getContentType()->getName().':'.$revision->getOuuid().' is deleted and is referenced in '.$environment->getName());
				}
				else if ($revision->getContentType()->getDeleted()) {
					++$deleted;
					$this->session->getFlashBag()->add('warning', 'The content type of the revision '.$revision->getContentType()->getName().':'.$revision->getOuuid().' is deleted and is referenced in '.$environment->getName());
				}
				else if ($revision->getContentType()->getEnvironment() == $environment && $revision->getEndTime() != null) {
					++$error;
					$this->session->getFlashBag()->add('warning', 'The revision '.$revision->getId().' of '.$revision->getContentType()->getName().':'.$revision->getOuuid().' as an end date but '.$environment->getName().' is its defualt environment');
				}
				else {
					
					$config = [
						'index' => $index,
						'id' => $revision->getOuuid(),
						'type' => $revision->getContentType()->getName(),
						'body' => $revision->getRawData()
					];
					
					if($revision->getContentType()->getHavePipelines()){
						$config['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
					}
					
					$status = $this->client->index($config);
					if($status["_shards"]["failed"] == 1) {
						$error++;
					} else {
						$count++;				
					}
				}
				$progress->advance();
			}
			$progress->finish();
			$output->writeln('');

			$output->writeln(' '.$count.' objects are reindexed in '.$index.' ('.$deleted.' not indexed as deleted, '.$error.' with indexing error)');
			$this->flushFlash($output);
			
		}
		else{
			$output->writeln("WARNING: Environment named ".$name." not found");
		}
    }
}