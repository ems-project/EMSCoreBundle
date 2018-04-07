<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Service\EnvironmentService;

class RebuildCommand extends EmsCommand
{
	private $mapping;
	private $doctrine;
	private $container;

	/**@var ContentTypeService*/
	private $contentTypeService;
	/**@var EnvironmentService*/
	private $environmentService;
	private $instanceId;
    private $singleTypeIndex;

	public function __construct(Registry $doctrine, Logger $logger, Client $client, $mapping, Container $container, Session $session, $instanceId, $singleTypeIndex)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
		$this->client = $client;
		$this->mapping = $mapping;
		$this->container = $container;
		$this->contentTypeService = $container->get('ems.service.contenttype');
		$this->environmentService = $container->get('ems.service.environment');
		$this->session = $session;
		$this->instanceId = $instanceId;
        $this->singleTypeIndex = $singleTypeIndex;
		parent::__construct($logger, $client, $session);
	}

	protected function configure()
    {
        $this
            ->setName('ems:environment:rebuild')
            ->setDescription('Rebuild an environment in a brand new index')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Environment name'
            )
            ->addOption(
                'yellow-ok',
                null,
                InputOption::VALUE_NONE,
                'Agree to rebuild on a yellow status cluster'
            )
            ->addOption(
            	'sign-data',
            	null,
            	InputOption::VALUE_NONE,
            	'The content will be (re)signed during the rebuilding process'
            )
            ->addOption(
            	'bulk-size',
            	null,
            	InputOption::VALUE_OPTIONAL,
            	'Number of item that will be indexed together during the same elasticsearch operation',
            	1000
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$this->formatFlash($output);

    	if( ! $input->getOption('yellow-ok') ){
    		$this->waitForGreen($output);
    	}
    	
    	
    	$signData= $input->getOption('sign-data');

		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
		/** @var  Client $client */
		$client = $this->client;
		$name = $input->getArgument('name');
		/** @var JobRepository $envRepo */
		$envRepo = $em->getRepository('EMSCoreBundle:Environment');
		/** @var Environment $environment */
		$environment = $envRepo->findBy(['name' => $name, 'managed' => true]);
		if($environment && count($environment) == 1) {
			$environment = $environment[0];
			if($environment->getAlias() != $this->instanceId.$environment->getName()) {
				$environment->setAlias($this->instanceId.$environment->getName());
				$em->persist($environment);
				$em->flush();
				$output->writeln("Alias has been aligned to ".$environment->getAlias());
			}

			$indexName = $environment->getAlias().AppController::getFormatedTimestamp();


			/** @var \EMS\CoreBundle\Repository\ContentTypeRepository $contentTypeRepository */
			$contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');
			$contentTypes = $contentTypeRepository->findAll();
			/** @var ContentType $contentType */


			$client->indices()->create([
					'index' => $indexName,
					'body' => $this->environmentService->getIndexAnalysisConfiguration(),
			]);

			$output->writeln('A new index '.$indexName.' has been created');
	    	if( ! $input->getOption('yellow-ok') ){
	    		$this->waitForGreen($output);
	    	}

			// create a new progress bar
			$progress = new ProgressBar($output, count($contentTypes));
			// start and displays the progress bar
			$progress->start();

			/** @var ContentType $contentType */
			foreach ($contentTypes as $contentType){
				if(!$contentType->getDeleted() && $contentType->getEnvironment() && $contentType->getEnvironment()->getManaged()){
					$this->contentTypeService->updateMapping($contentType, $indexName);
				}

				$progress->advance();
			}
			$progress->finish();
			$output->writeln('');

			$this->flushFlash($output);

			$command = $this->getReindexCommand();
			$command->reindex($name, $indexName, $output, $signData, $input->getOption('bulk-size'));

// 			$arguments = array(
// 					'name'    => $name,
// 					'index'   => $indexName
// 			);

// 			$reindexInput = new ArrayInput($arguments);
// 			$returnCode = $command->run($reindexInput, $output);

// 			if($returnCode){
// 				$output->writeln('Reindexed with return code: '.$returnCode);
// 			}

	    	if( ! $input->getOption('yellow-ok') ){
	    		$this->waitForGreen($output);
	    	}
			$this->switchAlias($environment->getAlias(), $indexName, true, $output);
			$output->writeln('The alias <info>'.$environment->getName().'</info> is now pointing to '.$indexName);
		}
		else{
			$output->writeln("WARNING: Environment named ".$name." not found");
		}
		$this->flushFlash($output);
    }


    /*
     * @return ReindexCommand
     */
    protected function getReindexCommand() {
    	return $this->container->get('ems.environment.reindex');
    }

    /**
     * Update the alias of an environement to a new index
     *
     * @param string $alias
     * @param string $to
     */
    private function switchAlias($alias, $to, $newEnv=false, OutputInterface $output){
    	try{


    		$result = $this->client->indices()->getAlias(['name' => $alias]);
    		$params ['body']['actions'] = [];

    		foreach ($result as $id => $item){
    			$params ['body']['actions'][] = [
    				'remove' => [
	    				"index" => $id,
	    				"alias" => $alias,
    				]
    			];
    		}

    		$params ['body']['actions'][] = [
    			'add' => [
	    			'index' => $to,
	    			'alias' => $alias,
    			]
    		];

    		$this->client->indices()->updateAliases ( $params );
    	}
    	catch(\Exception $e){ //TODO why does Elasticsearch\Common\Exceptions\Missing404Exception is not catched?

    		if(!$newEnv){
    			$output->writeln ( 'WARNING : Alias '.$alias.' not found' );
    		}
    		$this->client->indices()->putAlias([
    				'index' => $to,
    				'name' => $alias
    		]);
    	}

    }
}
