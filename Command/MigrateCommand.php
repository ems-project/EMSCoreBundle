<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Mapping;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Exception\CantBeFinalizedException;

class MigrateCommand extends EmsCommand
{
	protected $client;
	/**@var Mapping */
	protected $mapping;
	protected $doctrine;
	protected $logger;
	protected $container;
	protected $dataService;
	/**@var FormFactoryInterface $formFactory*/
	protected $formRegistry;
	
	protected $instanceId;
	
	public function __construct(Registry $doctrine, Logger $logger, Client $client, $mapping, DataService $dataService, FormFactoryInterface $formFactory, $instanceId, Session $session)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
		$this->client = $client;
		$this->mapping = $mapping;
		$this->dataService = $dataService;
		$this->formFactory= $formFactory;
		$this->instanceId = $instanceId;
		parent::__construct($logger, $client, $session);
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
    
    
    private function getSubmitData(Form $form){
    	return $this->dataService->getSubmitData($form);
    }
    
    /**
     * 
     * @return \EMS\CoreBundle\Entity\Revision
     */
    private function getEmptyRevision(ContentType $contentType) {
    	return $this->dataService->getEmptyRevision($contentType, 'SYSTEM_MIGRATE');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
		//https://stackoverflow.com/questions/9699185/memory-leaks-symfony2-doctrine2-exceed-memory-limit
		//https://stackoverflow.com/questions/13093689/is-there-a-way-to-free-the-memory-allotted-to-a-symfony-2-form-object
		$em->getConnection()->getConfiguration()->setSQLLogger(null);
		
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
		$defaultEnv = $contentTypeTo->getEnvironment();
		
    	$output->writeln("Start migration of ".$contentTypeTo->getPluralName());
		
    	if($contentTypeTo->getDirty()) {
			$output->writeln("<error>Content type \"".$contentTypeNameTo."\" is dirty. Please clean it first</error>");
			exit;
		}
		if(!$input->getOption('force') && strcmp($defaultEnv->getAlias(), $elasticsearchIndex) === 0 && strcmp($contentTypeNameFrom, $contentTypeNameTo) === 0) {
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
			        'body' => '{
                       "sort": {
                          "_uid": {
                             "order": "asc",
                             "missing": "_last"
                          }
                       }
                    }',
					//'preference' => '_primary', //http://stackoverflow.com/questions/10836142/elasticsearch-duplicate-results-with-paging
			]);
			
			$contentTypeTo = $contentTypeRepository->findOneBy(array("name" => $contentTypeNameTo, 'deleted' => false));
			$defaultEnv = $contentTypeTo->getEnvironment();

			foreach ($arrayElasticsearchIndex["hits"]["hits"] as $index => $value) {
				try{
					$newRevision = $this->getEmptyRevision($contentTypeTo);
					$newRevision->setOuuid($value['_id']);
					$until = $newRevision->getLockUntil();
					$now = $newRevision->getStartTime();
					
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
							$newRevision->setRawData($value['_source']);
							$revisionType = $this->formFactory->create(RevisionType::class, $newRevision, ['migration' => true]);
							$viewData = $this->getSubmitData($revisionType->get('data'));
							
							$revisionType->setData($currentRevision);
							
							$revisionType->submit(['data' => $viewData]);
							$data = $revisionType->get('data')->getData();
							$newRevision->setData($data);
							$objectArray = $newRevision->getRawData();

							$this->dataService->propagateDataToComputedField($revisionType->get('data'), $objectArray, $contentTypeTo, $contentTypeTo->getName(), $value['_id'], true);
							$newRevision->setRawData($objectArray);
							
							unset($revisionType);
							
						}
						
						$currentRevision->setEndTime($now);
						$currentRevision->setDraft(false);
						$currentRevision->setAutoSave(null);
						$currentRevision->removeEnvironment($defaultEnv);
						$currentRevision->setLockBy('SYSTEM_MIGRATE');
						$currentRevision->setLockUntil($until);
						$em->persist($currentRevision);
					}	
					else if($input->getOption('raw')){
						$newRevision->setRawData($value['_source']);
						$objectArray = $value['_source'];
					}
					else{
						$newRevision->setRawData($value['_source']);
						$revisionType = $this->formFactory->create(RevisionType::class, $newRevision, ['migration' => true]);
						$viewData = $this->getSubmitData($revisionType->get('data'));
						
						$revisionType->setData($this->getEmptyRevision($contentTypeTo));
						$revisionType->submit(['data' => $viewData]);
						$data = $revisionType->get('data')->getData();
						$newRevision->setData($data);
						$objectArray = $newRevision->getRawData();
						
						$this->dataService->propagateDataToComputedField($revisionType->get('data'), $objectArray, $contentTypeTo, $contentTypeTo->getName(), $value['_id'], true);
						$newRevision->setRawData($objectArray);
						
						unset($revisionType);
					}
					
					$this->dataService->setMetaFields($newRevision);
					
					
					$indexConfig = [
							'index' => $defaultEnv->getAlias(),
							'type' => $contentTypeNameTo,
							'id' => $value['_id'],
							'body' => $this->dataService->sign($newRevision),
					];
					
					if($newRevision->getContentType()->getHavePipelines()){
						$indexConfig['pipeline'] = $this->instanceId.$contentTypeNameTo;
					}
					
					$this->client->index($indexConfig);
					
					$newRevision->setDraft(false);
					//TODO: Test if client->index OK
					$em->persist($newRevision);
 					$revisionRepository->finaliseRevision($contentTypeTo, $value['_id'], $now);
					//hot fix query: insert into `environment_revision`  select id, 1 from `revision` where `end_time` is null;
					$revisionRepository->publishRevision($newRevision);
				}
				catch(NotLockedException $e){
					$output->writeln("<error>'.$e.'</error>");
				}
				catch(CantBeFinalizedException $e){
					$output->writeln("<error>'.$e.'</error>");
				}

				$this->flushFlash($output);
				
				
				// advance the progress bar 1 unit
				$progress->advance();
				$em->flush();
			}
			$revisionRepository->clear();
			$contentTypeRepository->clear();
			$em->clear();
			unset($defaultEnv);
			unset($contentTypeTo);
			
			
		}
		// ensure that the progress bar is at 100%
		$progress->finish();
		$output->writeln("");
		$this->flushFlash($output);
		$output->writeln("Migration done");
    }
}