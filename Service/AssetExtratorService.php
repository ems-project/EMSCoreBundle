<?php

namespace EMS\CoreBundle\Service;



class AssetExtratorService
{
	
	const HELLO_EP = '/tika';
	
	
	/**@var string */
	private $tikaServer;
	
	/**@var RestClientService $rest*/
	private $rest;
	
	
	
	/**
	 * 
	 * @param string $tikaServer
	 */
	public function __construct(RestClientService $rest, $tikaServer)
	{
		$this->tikaServer = $tikaServer;
		$this->rest = $rest;
	}
	
	public function hello() {
		if($this->tikaServer){
			
			$client = $this->rest->getClient($this->tikaServer);
			$result = $client->get(self::HELLO_EP);
			return [
					'code' => $result->getStatusCode(),
					'content' => $result->getBody(),
			];
		}
		else {
			return null;
		}
	}
}