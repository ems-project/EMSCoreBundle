<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\Mapping;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use EMS\CoreBundle\Service\DataService;
use Symfony\Component\Console\Helper\ProgressBar;

class MigrateCommand extends ContainerAwareCommand
{
	protected $client;
	/**@var Mapping */
	protected $mapping;
	protected $doctrine;
	protected $logger;
	protected $container;
	protected $dataService;
	
	public function __construct(Registry $doctrine, Logger $logger, Client $client, $mapping, DataService $dataService)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
		$this->client = $client;
		$this->mapping = $mapping;
		$this->dataService = $dataService;
		parent::__construct();
	}
	
	protected function configure()
    {
    	$this
            ->setName('ems:contenttype:migrate')
            ->setDescription('Migrate a content type from an elasticsearch index')
            ->addArgument(
                'contentTypeNameFrom',
                InputArgument::REQUIRED,
                'Content type name to migrate from'
            )
            ->addArgument(
                'contentTypeNameTo',
                InputArgument::REQUIRED,
                'Content type name to migrate into'
            )
            ->addArgument(
                'elasticsearchIndex',
                InputArgument::REQUIRED,
                'Elasticsearch index where to find ContentType objects as new source'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Allow to import from the default environment'
            )
            ->addOption(
                'raw',
                null,
                InputOption::VALUE_NONE,
                'The content will be imported as is. Without any field validation, data stripping or field protection'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
    	$contentTypeNameFrom = $input->getArgument('contentTypeNameFrom');
    	$contentTypeNameTo = $input->getArgument('contentTypeNameTo');
    	$elasticsearchIndex = $input->getArgument('elasticsearchIndex');
    	
		/** @var RevisionRepository $revisionRepository */
		$revisionRepository = $em->getRepository( 'EMSCoreBundle:Revision' );
		/** @var \EMS\CoreBundle\Repository\ContentTypeRepository $contentTypeRepository */
		$contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

		/** @var \EMS\CoreBundle\Entity\ContentType $contentTypeTo */
		$contentTypeTo = $contentTypeRepository->findOneBy(array("name" => $contentTypeNameTo, 'deleted' => false));
		if(!$contentTypeTo) {
			$output->writeln("<error>Content type ".$contentTypeNameTo." not found</error>");
			exit;
		}
    	$output->writeln("Start migration of ".$contentTypeTo->getPluralName());
		
    	if($contentTypeTo->getDirty()) {
			$output->writeln("<error>Content type \"".$contentTypeNameTo."\" is dirty. Please clean it first</error>");
			exit;
		}
		if(!$input->getOption('force') && strcmp($contentTypeTo->getEnvironment()->getAlias(), $elasticsearchIndex) === 0 && strcmp($contentTypeNameFrom, $contentTypeNameTo) === 0) {
			$output->writeln("<error>You can not import a content type on himself with the --force option</error>");
			exit;
		}
		
		$arrayElasticsearchIndex = $this->client->search([
				'index' => $elasticsearchIndex,
				'type' => $contentTypeNameFrom,
				'size' => 1
		]);
		
		$total = $arrayElasticsearchIndex["hits"]["total"];
		// create a new progress bar
		$progress = new ProgressBar($output, $total);
		// start and displays the progress bar
		$progress->start();
		
		
		for($from = 0; $from < $total; $from = $from + 50) {
			$arrayElasticsearchIndex = $this->client->search([
					'index' => $elasticsearchIndex,
					'type' => $contentTypeNameFrom,
					'size' => 50,
					'from' => $from,
					'preference' => '_primary', //http://stackoverflow.com/questions/10836142/elasticsearch-duplicate-results-with-paging
			]);
// 			$output->writeln("\nMigrating " . ($from+1) . " / " . $total );


			foreach ($arrayElasticsearchIndex["hits"]["hits"] as $index => $value) {
				try{
					$now = new \DateTime();
					$until = $now->add(new \DateInterval("PT5M"));//+5 minutes
					$newRevision = new Revision();
					$newRevision->setContentType($contentTypeTo);
					$newRevision->addEnvironment($contentTypeTo->getEnvironment());
					$newRevision->setOuuid($value['_id']);
					$newRevision->setStartTime($now);
					$newRevision->setEndTime(null);
					$newRevision->setDeleted(false);
					$newRevision->setDraft(true);
					$newRevision->setLockBy('SYSTEM_MIGRATE');
					$newRevision->setLockUntil($until);
						
					/**@var Revision $currentRevision*/
					$currentRevision = $revisionRepository->getCurrentRevision($contentTypeTo, $value['_id']);
					if($currentRevision) {
						if($input->getOption('raw')){
							$newRevision->setRawData($value['_source']);
							$objectArray = $value['_source'];
						}
						else {
							//If there is a current revision, datas in fields that are protected against migration must not be overridden
							//So we load the datas from the current revision into the next revision
							$newRevision->setRawData($currentRevision->getRawData());
							//We build the new revision object
							$this->dataService->loadDataStructure($newRevision);
							//We update the new revision object with the new datas. Here, the protected fields are not overridden.
							$newRevision->getDataField()->updateDataValue($value['_source'], true);//isMigrate=true
							//We serialize the new object
							$objectArray = $this->mapping->dataFieldToArray($newRevision->getDataField());
							$newRevision->setRawData($objectArray);
						}
						
						$currentRevision->setEndTime($now);
						$currentRevision->setDraft(false);
						$currentRevision->setAutoSave(null);
						$currentRevision->removeEnvironment($contentTypeTo->getEnvironment());
						$currentRevision->setLockBy('SYSTEM_MIGRATE');
						$currentRevision->setLockUntil($until);
						$em->persist($currentRevision);
					}	
					else if($input->getOption('raw')){
						$newRevision->setRawData($value['_source']);
						$objectArray = $value['_source'];
					}
					else{
						$newRevision->setRawData([]);
						$this->dataService->loadDataStructure($newRevision);
						$newRevision->getDataField()->updateDataValue($value['_source'], true);
						//We serialize the new object
						$objectArray = $this->mapping->dataFieldToArray($newRevision->getDataField());
						$newRevision->setRawData($objectArray);
					}
					
					$this->dataService->setMetaFields($newRevision);
					
					$this->client->index([
							'index' => $contentTypeTo->getEnvironment()->getAlias(),
							'type' => $contentTypeNameTo,
							'id' => $value['_id'],
							'body' => $objectArray,
					]);
					//TODO: Test if client->index OK
					$em->persist($newRevision);
					$em->flush();
 					$revisionRepository->finaliseRevision($contentTypeTo, $value['_id'], $now);
					//hot fix query: insert into `environment_revision`  select id, 1 from `revision` where `end_time` is null;
					$revisionRepository->publishRevision($newRevision);
				}
				catch(NotLockedException $e){
					$output->writeln("<error>'.$e.'</error>");
				}

				// advance the progress bar 1 unit
				$progress->advance();
			}
			$revisionRepository->clear();
		}
		// ensure that the progress bar is at 100%
		$progress->finish();
		$output->writeln("");
		$output->writeln("Migration done");
    }
}