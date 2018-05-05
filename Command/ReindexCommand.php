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
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Entity\ContentType;

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
	
	private $count;
	private $deleted;
	private $error;
	
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
		
		$this->count = 0;
		$this->deleted = 0;
		$this->error = 0;
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
                'content-type',
                InputArgument::OPTIONAL,
                'If not defined all content types will be reindexed'
            )
            ->addArgument(
                'index',
                InputArgument::OPTIONAL,
                'Elasticsearch index where to index environment objects'
            )
            ->addOption(
            	'sign-data',
            	null,
            	InputOption::VALUE_NONE,
            	'The content won\'t be (re)signed during the reindexing process'
            )
            ->addOption(
            	'bulk-size',
            	null,
            	InputOption::VALUE_OPTIONAL,
            	'Number of item that will be indexed together during the same elasticsearch operation',
            	1000
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$this->formatFlash($output);
    	$name = $input->getArgument('name');
    	$index = $input->getArgument('index');
    	$signData= !$input->getOption('sign-data');



        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $ctRepo */
        $ctRepo = $em->getRepository('EMSCoreBundle:ContentType');

        $contentTypes = [];
        if($input->hasArgument('content-type')) {
            $contentTypes = $ctRepo->findBy(['deleted' => false, 'name' => $input->getArgument('content-type')]);
        }
        else {
            $contentTypes = $ctRepo->findBy(['deleted' => false]);
        }


        /**@var ContentType $contentType*/
        foreach ($contentTypes as $contentType) {

            $this->reindex($name, $contentType, $index, $output, $signData, $input->getOption('bulk-size'));
        }
    }
    
    public function reindex($name, ContentType $contentType, $index, OutputInterface $output, $signData=true, $bulkSize=1000)
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
		/** @var ContentTypeRepository $ctRepo */
		$ctRepo = $em->getRepository('EMSCoreBundle:ContentType');
		
		if($environment && count($environment) == 1) {
			$environment = $environment[0];
			
			if(!$index) {
				$index = $environment->getAlias();
			}
            $page = 0;
            $bulk = [];
            $paginator = $revRepo->getRevisionsPaginatorPerEnvironmentAndContentType($environment, $contentType, $page);



            $output->writeln('');
            $output->writeln('Start reindex '.$contentType->getName());
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
                    else {

                        if($signData) {
                            $this->dataService->sign($revision);
                            try{
                                $em->persist($revision);
                            }
                            catch (\Exception $e){
                            }
                        }

                        if(empty($bulk) && $revision->getContentType()->getHavePipelines()){
                            $bulk['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
                        }

                        $bulk['body'][] = [
                                'index' => [
                                    '_index' => $index,
                                    '_type' => $contentType->getName(),
                                    '_id' => $revision->getOuuid(),
                                ]
                            ];

                        $bulk['body'][] = $revision->getRawData();




                    }

                    if(count($bulk['body']) >= (2*$bulkSize)) {
                        $this->treatBulkResponse($this->client->bulk($bulk));
                        $bulk = [];
                    }

                    $progress->advance();
                }
                $em->clear(Revision::class);
                $this->flushFlash($output);

                ++$page;
                $paginator = $revRepo->getRevisionsPaginatorPerEnvironmentAndContentType($environment, $contentType, $page);
            } while ($paginator->getIterator()->count());


            if(count($bulk)) {
                $this->treatBulkResponse($this->client->bulk($bulk));
                $bulk = [];
            }
			
			$progress->finish();
			$output->writeln('');

			$output->writeln(' '.$this->count.' objects are reindexed in '.$index.' ('.$this->deleted.' not indexed as deleted, '.$this->error.' with indexing error)');
			$this->flushFlash($output);
			
		}
		else{
			$output->writeln("WARNING: Environment named ".$name." not found");
		}
    }
    
    public function treatBulkResponse($response) {
    	foreach ($response['items'] as $item){
    		if(isset($item['index']['error'])) {
    			++$this->error;
    			$this->session->getFlashBag()->add('warning', 'The revision '.$item['index']['_type'].':'.$item['index']['_id'].' throw an error during index:'.(isset($item['index']['error']['reason'])?$item['index']['error']['reason']:''));
    		}
    		else {
    			++$this->count;
    		}
    	}
    }
}