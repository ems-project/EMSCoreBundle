<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\JobRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use EMS\CoreBundle\Service\DataService;
use Elasticsearch\Common\Exceptions\ServerErrorResponseException;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Entity\Revision;
use Symfony\Component\Console\Input\InputOption;

class ReindexCommand extends EmsCommand
{
	protected $client;
	protected $mapping;
	protected $doctrine;
	protected $logger;
	protected $container;
	/**@var DataService*/
	protected $dataService;
	private $instanceId;
	
	public function __construct(Registry $doctrine, Logger $logger, Client $client, $mapping, $container, $instanceId, Session $session, DataService $dataService)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
		$this->client = $client;
		$this->mapping = $mapping;
		$this->container = $container;
		$this->instanceId = $instanceId;
		$this->dataService = $dataService;
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
            )
            ->addOption(
            	'dont-sign-data',
            	null,
            	InputOption::VALUE_NONE,
            	'The content won\'t be (re)signed during the reindexing process'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$this->formatFlash($output);
    	$name = $input->getArgument('name');
    	$index = $input->getArgument('index');
    	$signData= !$input->getOption('dont-sign-data');
    	$this->reindex($name, $index, $output, $signData);
    }
    
    public function reindex($name, $index, OutputInterface $output, $signData=true)
    {
    	$this->logger->info('Execute the ReindexCommand');
    	/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
		
		$em->getConnection()->getConfiguration()->setSQLLogger(null);
		
		/** @var EnvironmentRepository $envRepo */
		$envRepo = $em->getRepository('EMSCoreBundle:Environment');
		/** @var RevisionRepository $revRepo */
		$revRepo = $em->getRepository('EMSCoreBundle:Revision');
		/** @var Environment $environment */
		$environment = $envRepo->findBy(['name' => $name, 'managed' => true]);
		if($environment && count($environment) == 1) {
			$environment = $environment[0];
			
			if(!$index) {
				$index = $environment->getAlias();
			}
			
			$count = 0;
			$deleted = 0;
			$error = 0;
			
			$output->write('Start reindex');
			
			$page = 0;
			$paginator = $revRepo->getRevisionsPaginatorPerEnvironment($environment, $page);
			
			// create a new progress bar
			$progress = new ProgressBar($output, $paginator->count());
			// start and displays the progress bar
			$progress->start();
			do {
    			/** @var \EMS\CoreBundle\Entity\Revision $revision */
    			foreach ($paginator as $revision) {
    				if($revision->getDeleted()){
    					++$deleted;
    					$this->session->getFlashBag()->add('warning', 'The revision '.$revision->getContentType()->getName().':'.$revision->getOuuid().' is deleted and is referenced in '.$environment->getName());
    				}
    				else if ($revision->getContentType()->getDeleted()) {
    					++$deleted;
    					$this->session->getFlashBag()->add('warning', 'The content type of the revision '.$revision->getContentType()->getName().':'.$revision->getOuuid().' is deleted and is referenced in '.$environment->getName());
    				}
    // 				else if ($revision->getContentType()->getEnvironment() == $environment && $revision->getEndTime() != null) {
    // 					++$error;
    // 					$this->session->getFlashBag()->add('warning', 'The revision '.$revision->getId().' of '.$revision->getContentType()->getName().':'.$revision->getOuuid().' as an end date but '.$environment->getName().' is its default environment');
    // 				}
    				else {
    					
    					$config = [
    						'index' => $index,
    						'id' => $revision->getOuuid(),
    						'type' => $revision->getContentType()->getName(),
    						'body' => $signData?$this->dataService->sign($revision):$revision->getRawData(),
    					];
    					
    					if($signData) {
	    					try{
	    						$em->persist($revision);
	//     						$em->flush($revision);						
	    					}
	    					catch (\Exception $e){
	    						
	    					}
    					}
    					
    					if($revision->getContentType()->getHavePipelines()){
    						$config['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
    					}
    					try {
    						$status = $this->client->index($config);
    						if($status["_shards"]["failed"] == 1) {
    							$error++;
    						} else {
    							$count++;				
    						}						
    					}
    					catch(BadRequest400Exception $e){
    						$this->session->getFlashBag()->add('warning', 'The revision '.$revision->getId().' of '.$revision->getContentType()->getName().':'.$revision->getOuuid().' through an error during indexing');
    						$error++;
    					}
    					catch (ServerErrorResponseException $e){
    					    $output->writeln($revision->getContentType()->getName().':'.$revision->getOuuid().': '.$e->getMessage());
     					    $this->session->getFlashBag()->add('warning', 'The revision '.$revision->getId().' of '.$revision->getContentType()->getName().':'.$revision->getOuuid().' through an error during indexing');
    					    $error++;
    					}
    				}
    				$em->detach($revision);
    				$progress->advance();
    			}
    			$em->clear(Revision::class);
    			$this->flushFlash($output);
    			
    			++$page;
    			$paginator = $revRepo->getRevisionsPaginatorPerEnvironment($environment, $page);
		    } while ($paginator->getIterator()->count());
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