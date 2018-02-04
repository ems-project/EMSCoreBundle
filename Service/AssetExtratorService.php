<?php

namespace EMS\CoreBundle\Service;

use Circle\RestClientBundle\Services\RestClient;

class AssetExtratorService
{
	
	const HELLO_EP = '/tika';
	
	
	/**@var string */
	private $tikaServer;
	
	/**@var RestClient $rest*/
	private $rest;
	
	
	
	/**
	 * 
	 * @param string $tikaServer
	 */
	public function __construct(RestClient $rest, $tikaServer)
	{
		$this->tikaServer = $tikaServer;
		$this->rest = $rest;
	}
	
	public function hello() {
		if($this->tikaServer){
			/**@var \Symfony\Component\HttpFoundation\Response $result*/
			$result = $this->rest->get($this->tikaServer.self::HELLO_EP);
			return [
					'code' => $result->getStatusCode(),
					'content' => $result->getContent(),
			];
		}
		else {
			return null;
		}
	}
}