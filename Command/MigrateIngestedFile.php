<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\ProgressBar;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\AssetExtratorService;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Console\Input\InputOption;

class MigrateIngestedFile extends EmsCommand
{
	
	/**@var Registry */
	protected $doctrine;
	/**@var ContentTypeService */
	protected $contentTypeService;
	/**@var  AssetExtratorService */
	protected $extractorService;
	protected $databaseName;
	protected $databaseDriver;
	/**@var FileService */
	protected $fileService;
	 
	
	public function __construct(Logger $logger, Client $client, Session $session, Registry $doctrine, ContentTypeService $contentTypeService, $databaseName, $databaseDriver, AssetExtratorService $extractorService, FileService $fileService)
	{
		$this->doctrine = $doctrine;
		$this->contentTypeService = $contentTypeService;
		$this->extractorService = $extractorService;
		$this->databaseName = $databaseName;
		$this->databaseDriver = $databaseDriver;
		$this->fileService = $fileService;
		parent::__construct($logger, $client, $session, $databaseName, $databaseDriver);
	}
	
	protected function configure()
	{
		$this
		->setName('ems:revisions:migrate-ingested-file-fields')
		->setDescription('Migrate an ingested file field from an elasticsearch index')
		->addArgument(
			'contentType',
			InputArgument::REQUIRED,
			'Content type name to migrate'
		)
		->addArgument(
			'field',
			InputArgument::REQUIRED,
			'Field name to migrate'
		)
		->addOption(
		    'only-with-ingested-content',
		    null,
		    InputOption::VALUE_NONE,
		    'Will migrated filed with content subfield only (should be an old ingested asset field)'
	    );
	}
	
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln("Please do a backup of your DB first!");
		$helper = $this->getHelper('question');
		$question= new ConfirmationQuestion('Continue?', false);
		
		if(!$helper->ask($input, $output, $question)){
			return;
 		}
		
		
		$contentTypeName = $input->getArgument('contentType');
		$fieldName = $input->getArgument('field');
		
		
		$output->write('DB size before the migration : ');
		$this->dbSize($output);
		
		$contentType = $this->contentTypeService->getByName($contentTypeName);
		if( !$contentType) {
			throw new \Exception('Content type not found');
		}
		
		$onlyWithIngestedContent = $input->getOption('only-with-ingested-content');
		
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
		/** @var RevisionRepository $revisionRepository */
		$revisionRepository = $em->getRepository( 'EMSCoreBundle:Revision' );
		
		$total = $revisionRepository->countByContentType($contentType);
		
		$offset = 0;
		$limit = 10;
		
		
		// create a new progress bar
		$progress = new ProgressBar($output, $total);
		// start and displays the progress bar
		$progress->start();
		
		while(true) {
			$revisions = $revisionRepository->findBy(['contentType' => $contentType], ['id' => 'asc'], $limit, $offset);
			
			/**@var Revision $revision*/
			foreach ($revisions as $revision){
				$update = false;
				$rawData = $revision->getRawData();
				$newRawData = $this->findfield($rawData, $fieldName, $output, $onlyWithIngestedContent);
				if(!empty($rawData) && $rawData !== $newRawData) {
				    $revision->setRawData($newRawData);
				    $update = true;
				}
				$rawData = $revision->getAutoSave();
				$newRawData = $this->findfield($rawData, $fieldName, $output, $onlyWithIngestedContent);
				if(!empty($rawData) && $rawData !== $newRawData) {
				    $revision->setAutoSave($rawData);
				    $update = true;
				}
				
				if($update) {
					
					$revision->setLockBy('SYSTEM_MIGRATE');
					$date = new \DateTime();
					$date->modify('+5 minutes');
					$revision->setLockUntil($date);
					$em->persist($revision);
					$em->flush($revision);
				}
				
				$progress->advance();
			}
			
			if(count($revisions) == $limit){
				$offset += $limit;
			}
			else {
				break;
			}
			
		}
		
		$progress->finish();
		$output->writeln("");
		$this->flushFlash($output);
		$output->writeln("Migration done");
		$output->writeln("Please rebuild your environments and update your field type");
		
		$output->write('DB size after the migration : ');
		$this->dbSize($output);
		
	}
	
	private function findfield(array $rawData, $field, OutputInterface $output, $onlyWithIngestedContent=false)
	{
	    foreach ($rawData as $key => $data) {
	        if ($key === $field) {
	            $rawData[$key] = $this->migrate($data, $output, $onlyWithIngestedContent);
	        } elseif (is_array($data)) {
	            $rawData[$key] = $this->findfield($data, $field, $output, $onlyWithIngestedContent);
	        }
	    }
	    return $rawData;
	}

	private function migrate(array $rawData, OutputInterface $output, $onlyWithIngestedContent)
	{
	    if(!empty($rawData) && !empty($rawData)) {
	        
	        if(isset($rawData['content'])) {
	            unset($rawData['content']);
	        }
	        else if($onlyWithIngestedContent) {
	            return $rawData;
	        }
	        
	        if(isset($rawData['sha1'])) {
	            $file = $this->fileService->getFile($rawData['sha1']);
	            if($file) {
	                $data = $this->extractorService->extractData($file, isset($rawData['filename'])?$rawData['filename']:'filename');
	                
	                if(!empty($data)) {
	                    if(isset($data['date']) && $data['date']) {
	                        $rawData['_date'] = $data['date'];
	                    }
	                    if(isset($data['content']) && $data['content']) {
	                        $rawData['_content'] = $data['content'];
	                    }
	                    if(isset($data['Author']) && $data['Author']) {
	                        $rawData['_author'] = $data['Author'];
	                    }
	                    if(isset($data['author']) && $data['author']) {
	                        $rawData['_author'] = $data['author'];
	                    }
	                    if(isset($data['language']) && $data['language']) {
	                        $rawData['_language'] = $data['language'];
	                    }
	                    if(isset($data['title']) && $data['title']) {
	                        $rawData['_title'] = $data['title'];
	                    }
	                }
	            }
	            else {
	                $output->writeln('File not found:'.$rawData['sha1']);
	            }
	            
	        }
	    }
	    return $rawData;
	}
	
	private function dbSize(OutputInterface $output)
	{
		
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
		$query = '';
		
		if (in_array($this->databaseDriver, ['pdo_pgsql'])) {
			$query = "SELECT pg_size_pretty(pg_database_size('$this->databaseName')) AS size";
		}
		else if (in_array($this->databaseDriver, ['pdo_mysql'])){
			$query = "SELECT
			SUM(data_length + index_length)/1024/1024 AS size
			FROM information_schema.TABLES
			WHERE table_schema='$this->databaseName'
			GROUP BY table_schema";
		}
		else {
			throw new \Exception('Not supported driver');
		}
		$stmt = $em->getConnection()->prepare($query);
		$stmt->execute();
		$size = $stmt->fetchAll();
		$output->writeln($size[0]['size']);
	}
}