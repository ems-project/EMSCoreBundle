<?php

namespace EMS\CoreBundle\Service;


use EMS\CoreBundle\Entity\Audit;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use Monolog\Logger;

class AuditService {
	
	// index elasticSerach
	protected $index;
	/**@var Registry $doctrine */
	protected $doctrine;
	/**@var Client $client*/
	protected $client;
	/**@var UserService $userService*/
	protected $userService;
	/**@var Logger $logger*/
	protected $logger;
	
	public function __construct($index, Registry $doctrine, Client $client, UserService $userService, Logger $logger)
	{
		$this->index = $index;
		$this->doctrine = $doctrine;
		$this->client = $client;
		$this->userService = $userService;
		$this->logger = $logger;
		
	} 
	
	public function auditLog($action, $rawData, $environment = null) { 
		
		$date = new \DateTime();
		$userName = $this->userService->getCurrentUser()->getUserName();

		// if index is define (insert in index)
		if ($this->index != null) {
			$this->auditLogToIndex($action, $rawData, $environment);
		} else {
			// insert in DB
			$this->auditLogToDB($action, $rawData, $environment);
		}
	}
	
	public function auditLogToIndex($action, $rawData, $environment = null){
		try{
			$date = new \DateTime();
			$userName = $this->userService->getCurrentUser()->getUserName();
			$objectArray = ["action" => $action,
					"date" => $date,
					"raw_data" => serialize($rawData),
					"user" => $userName,
					"environment" => $environment
			];
			
			$status = $this->client->create([
					'index' => $this->index . '_' . date_format($date, 'Ymd'),
					'type' => 'Audit',
					'body' => $objectArray
			]);
		}
		catch(\Exception $e){
			$this->logger->err('An error occured: '.$e->getMessage());
		}
	}
	
	public function auditLogToDB($action, $rawData, $environment = null){
		try{
			$audit = new Audit();
			$audit->setAction($action);
			$audit->setRawData(serialize($rawData));
			$audit->setEnvironment($environment);
			$date = new \DateTime();
			$audit->setDate($date);
			$userName = $this->userService->getCurrentUser()->getUserName();
			$audit->setUsername($userName);
			
			$em = $this->doctrine->getManager();
			$em->persist($audit);
			$em->flush();
		}
		catch(\Exception $e){
			$this->logger->err('An error occured: '.$e->getMessage());
		}
	}
}