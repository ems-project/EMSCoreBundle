<?php

namespace EMS\CoreBundle\Service\Storage;



use EMS\CoreBundle\Service\RestClientService;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use function intval;
use MongoDB\Driver\Exception\ExecutionTimeoutException;

class HttpStorage implements StorageInterface {

    /**@var RestClientService $restClient*/
    private $restClient;

    private $cacheDir;
    private $getUrl;
    private $postUrl;
    private $postFieldName;
    private $authKey;
	
	public function __construct(RestClientService $restClient, $cacheDir, $getUrl, $postUrl, $authKey=false, $postFieldName='upload') {
        $this->cacheDir = $cacheDir;
        $this->getUrl = $getUrl;
        $this->postUrl = $postUrl;
        $this->postFieldName = $postFieldName;
        $this->restClient = $restClient;
        $this->authKey = $authKey;
	}
	
	private function getCachePath($hash){
		$out = $this->cacheDir;
		$out.= DIRECTORY_SEPARATOR.substr($hash, 0, 3);

		if(!file_exists($out) ) {
			mkdir($out, 0777, true);
		}
		
		return $out.DIRECTORY_SEPARATOR.$hash;
	}
	
	public function head($hash, $cacheContext=false) {
	    if($cacheContext)
        {
            return false;
        }

        //https://stackoverflow.com/questions/1545432/what-is-the-easiest-way-to-use-the-head-command-of-http-in-php?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
	    try {
            $context  = stream_context_create(array('http' =>array('method'=>'HEAD')));
            $fd = fopen($this->getUrl.$hash, 'rb', false, $context);
            fclose($fd);
            return TRUE;
        }
        catch (Exception $e){
	        //So it's a FALSE
        }
		return FALSE;
	}
	
	public function create($hash, $filename, $cacheContext=FALSE){
        if($cacheContext)
        {
            return false;
        }

        $out = $this->getCachePath($hash);
        copy($filename, $out);

        try {

            $client = $this->restClient->getClient();

            $res = $client->request('POST', $this->postUrl, [
                'multipart' => [
                    [
                        'name'     => $this->postFieldName,
                        'contents' => fopen($filename, 'r'),
                    ]
                ],
                'headers' => [
                    'X-Auth-Token' => $this->authKey,
                ],

            ]);

        }
        catch (ClientException $e){
            return false;
        }
        catch (RequestException $e){
            return false;
        }

        return $out;
	}
	
	public function supportCacheStore() {
		return false;
	}
	
	public function read($hash, $cacheContext=false){
        if($cacheContext)
        {
            return false;
        }

		$out = $this->getCachePath($hash);
		if(file_exists($out)){
			return $out;
		}
		
		//https://stackoverflow.com/questions/3938534/download-file-to-server-from-url?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
        file_put_contents($out, fopen($this->getUrl.$hash, 'rb'));

		return fopen($out, 'rb');
	}
	
	public function getSize($hash, $cacheContext=false){
        if($cacheContext)
        {
            return false;
        }

        //https://stackoverflow.com/questions/1545432/what-is-the-easiest-way-to-use-the-head-command-of-http-in-php?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
        try {
            $context  = stream_context_create(array('http' =>array('method'=>'HEAD')));
            $fd = fopen($this->getUrl.$hash, 'rb', false, $context);

            $metas = stream_get_meta_data($fd);
            if(isset($metas['wrapper_data']))
            {
                foreach ($metas['wrapper_data'] as $meta){
                    if(preg_match('/^content\-length: (.*)$/', $meta, $matches, PREG_OFFSET_CAPTURE)){
                        return intval($matches[1][0]);
                    }
                }
            }
        }
        catch (Exception $e){
            //So it's a FALSE
        }
        return FALSE;
	}

	public function getLastUpdateDate($hash, $cacheContext=false){
        if($cacheContext)
        {
            return false;
        }

        //https://stackoverflow.com/questions/1545432/what-is-the-easiest-way-to-use-the-head-command-of-http-in-php?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
        try {
            $context  = stream_context_create(array('http' =>array('method'=>'HEAD')));
            $fd = fopen($this->getUrl.$hash, 'rb', false, $context);

            $metas = stream_get_meta_data($fd);
            if(isset($metas['wrapper_data']))
            {
                foreach ($metas['wrapper_data'] as $meta){
                    if(preg_match('/^Last\-Modified: (.*)$/', $meta, $matches, PREG_OFFSET_CAPTURE)){
                        return strtotime($matches[1][0]);
                    }
                }
            }
        }
        catch (Exception $e){
            //So it's a FALSE
        }
        return FALSE;
	}
	
	public function __toString()
    {
        return HttpStorage::class." ($this->getUrl - $this->postUrl)";
    }
}
