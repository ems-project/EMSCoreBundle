<?php

namespace EMS\CoreBundle\Service;



class AssetExtratorService
{
	
	const CONTENT_EP = '/tika';
	const HELLO_EP = '/tika';
	const META_EP = '/meta';
	
	
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
					'content' => $result->getBody()->__toString(),
			];
		}
		else {
			return null;
		}
	}
	
	public function extractData($file, $name) {
		if($this->tikaServer){
			$client = $this->rest->getClient($this->tikaServer);
			$body = file_get_contents($file);
			$result = $client->put(self::META_EP, [
					'body' => $body,
					'headers' => [
						'Accept' => 'application/json'
					],
			]);
			
			$out = json_decode($result->getBody()->__toString(), true);
			
			$result = $client->put(self::CONTENT_EP, [
					'body' => $body,
					'headers' => [
							'Accept' => 'text/plain',
					],
			]);
			
			$out['content'] = $result->getBody()->__toString();
			return $out;
			
		}
		return [
			"content" => "Tika server not configured",	
		];
	}
}