<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Exception\AssetNotFoundException;
use Symfony\Component\HttpFoundation\Session\Session;
use Enzim\Lib\TikaWrapper\TikaWrapper;

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

    /**@var Registry $doctrine */
    private $doctrine;

    /**@var FileService */
    private $fileService;
	
	
	/**
	 * 
	 * @param string $tikaServer
	 */
	public function __construct(RestClientService $rest, Session $session, Registry $doctrine, FileService $fileService, $tikaServer)
	{
		$this->tikaServer = $tikaServer;
		$this->rest = $rest;
		$this->session = $session;
        $this->doctrine = $doctrine;
        $this->fileService = $fileService;
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

    /**
     * @param string $sha1
     * @return array|mixed
     * @throws AssetNotFoundException
     */
	public function extractData($sha1, $file=null) {

        if(!$file || !file_exists($file)){
            $file = $this->fileService->getFile($sha1);
        }

        if(!$file || !file_exists($file)){
            throw new AssetNotFoundException($sha1);
        }

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
		else {
		    try {
    		    $out = AssetExtratorService::convertMetaToArray(TikaWrapper::getMetadata($file));
    		    if(!isset($out['content'])){
    		        $out['content'] =  mb_convert_encoding(TikaWrapper::getText($file), 'UTF-8', 'ASCII');
    		    }
    		    if(!isset($out['language'])){
        		    $out['language'] = AssetExtratorService::cleanString(TikaWrapper::getLanguage($file));
    		    }
    		    return $out;		        
		    }
		    catch (\Exception $e) {
                $this->session->getFlashBag()->add('warning', 'Error with Tika: '.$e->getMessage());
		    }
		}
		return $out;

	}
	
	static private function cleanString($string){
	    return preg_replace("/\n/", "", (preg_replace("/\r/", "", mb_convert_encoding($string, 'UTF-8', 'ASCII'))));
	}
	
	static private function convertMetaToArray($data) {
	    $cleaned = mb_convert_encoding($data, 'UTF-8', 'ASCII');
	    $cleaned = (preg_replace("/\r/", "", $cleaned));
	    $matches = [];
	    preg_match_all("/^(.*): (.*)$/m",
	        $cleaned,
	        $matches, PREG_PATTERN_ORDER );
	    
	    return array_combine($matches[1], $matches[2]);
	}
}