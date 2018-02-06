<?php

namespace EMS\CoreBundle\Service;

use Symfony\Component\HttpFoundation\Session\Session;

class AssetExtratorService
{
	
	const CONTENT_EP = '/tika';
	const HELLO_EP = '/tika';
	const META_EP = '/meta';
	
	
	/**@var string */
	private $tikaServer;
	
	/**@var RestClientService $rest*/
	private $rest;
	
	/**@var Session $session*/
	private $session;
	
	
	/**
	 * 
	 * @param string $tikaServer
	 */
	public function __construct(RestClientService $rest, Session $session, $tikaServer)
	{
		$this->tikaServer = $tikaServer;
		$this->rest = $rest;
		$this->session = $session;
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
		$out = [];
		if($this->tikaServer){
			try {
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
			}
			catch (\Exception $e) {
				$this->session->getFlashBag()->add('warning', 'elasticms encountered an issue while extracting file data: '.$e->getMessage());
			}
			return $out;
			
		}
		$this->session->getFlashBag()->add('warning', 'Tika server not configured');
		return $out;

	}
}