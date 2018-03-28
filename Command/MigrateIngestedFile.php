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
				if(!empty($rawData) && $this->migrate($rawData, $revision, $fieldName, $output, $onlyWithIngestedContent) ) {
					$revision->setRawData($rawData);
					$update = true;
				}
				
				$rawData = $revision->getAutoSave();
				if(!empty($rawData) && $this->migrate($rawData, $revision, $fieldName, $output, $onlyWithIngestedContent) ) {
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
	
	private function migrate(array &$rawData, Revision $revision, $field, OutputInterface $output, $onlyWithIngestedContent=false){
		$out = false;
		if(!empty($rawData) && !empty($rawData[$field])) {
			
			if(isset($rawData[$field]['content'])){
				unset($rawData[$field]['content']);
				$out = true;
			}
			else if($onlyWithIngestedContent) {
			    return false;
			}
			
			if(isset($rawData[$field]['sha1'])){
				$file = $this->fileService->getFile($rawData[$field]['sha1']);
				if($file) {
					$data = $this->extractorService->extractData($file, isset($rawData[$field]['filename'])?$rawData[$field]['filename']:'filename');
					
					if(!empty($data)) {
						if(isset($data['date']) && $data['date']) {
							$rawData[$field]['_date'] = $data['date'];
							$out = true;
						}
						if(isset($data['content']) && $data['content']) {
							$rawData[$field]['_content'] = $data['content'];
							$out = true;
						}
						if(isset($data['Author']) && $data['Author']) {
						    $rawData[$field]['_author'] = $data['Author'];
						    $out = true;
						}
						if(isset($data['author']) && $data['author']) {
						    $rawData[$field]['_author'] = $data['author'];
						    $out = true;
						}
						if(isset($data['language']) && $data['language']) {
							$rawData[$field]['_language'] = $data['language'];
							$out = true;
						}
						if(isset($data['title']) && $data['title']) {
							$rawData[$field]['_title'] = $data['title'];
							$out = true;
						}
					}
					
					
				}
				else {
					$output->writeln('File not found:'.$rawData[$field]['sha1']);	
				}
				
			}
			
			
		}
		return $out;
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