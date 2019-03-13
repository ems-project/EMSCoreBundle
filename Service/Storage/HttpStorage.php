<?php

namespace EMS\CoreBundle\Service\Storage;

use EMS\CoreBundle\Service\RestClientService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use function intval;

class HttpStorage implements StorageInterface
{

    /**@var RestClientService $restClient */
    private $restClient;

    private $getUrl;
    private $postUrl;
    private $postFieldName;
    private $authKey;

    public function __construct(RestClientService $restClient, $getUrl, $postUrl, $authKey = false, $postFieldName = 'upload')
    {
        $this->getUrl = $getUrl;
        $this->postUrl = $postUrl;
        $this->postFieldName = $postFieldName;
        $this->restClient = $restClient;
        $this->authKey = $authKey;
    }

    public function head($hash, $cacheContext = false)
    {
        if ($cacheContext) {
            return false;
        }

        //https://stackoverflow.com/questions/1545432/what-is-the-easiest-way-to-use-the-head-command-of-http-in-php?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
        try {
            $context = stream_context_create(array('http' => array('method' => 'HEAD')));
            $fd = fopen($this->getUrl . $hash, 'rb', false, $context);
            fclose($fd);
            return true;
        } catch (Exception $e) {
            //So it's a FALSE
        }
        return false;
    }

    public function create($hash, $filename, $cacheContext = false)
    {
        if ($cacheContext) {
            return false;
        }

        try {
            $client = $this->restClient->getClient();

            $client->request('POST', $this->postUrl, [
                'multipart' => [
                    [
                        'name' => $this->postFieldName,
                        'contents' => fopen($filename, 'r'),
                    ]
                ],
                'headers' => [
                    'X-Auth-Token' => $this->authKey,
                ],

            ]);
        } catch (GuzzleException $e) {
            return false;
        }

        return true;
    }

    public function supportCacheStore()
    {
        return false;
    }

    public function read($hash, $cacheContext = false)
    {
        if ($cacheContext) {
            return false;
        }

        try {
            //https://stackoverflow.com/questions/3938534/download-file-to-server-from-url?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
            return @fopen($this->getUrl . $hash, 'rb');
        } catch (Exception $e) {
            dump($e);
            return false;
        }
    }

    public function getSize($hash, $cacheContext = false)
    {
        if ($cacheContext) {
            return false;
        }

        //https://stackoverflow.com/questions/1545432/what-is-the-easiest-way-to-use-the-head-command-of-http-in-php?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
        try {
            $context = stream_context_create(array('http' => array('method' => 'HEAD')));
            $fd = fopen($this->getUrl . $hash, 'rb', false, $context);

            $metas = stream_get_meta_data($fd);
            if (isset($metas['wrapper_data'])) {
                foreach ($metas['wrapper_data'] as $meta) {
                    if (preg_match('/^content\-length: (.*)$/', $meta, $matches, PREG_OFFSET_CAPTURE)) {
                        return intval($matches[1][0]);
                    }
                }
            }
        } catch (Exception $e) {
            //So it's a FALSE
        }
        return false;
    }

    public function getLastUpdateDate($hash, $cacheContext = false)
    {
        if ($cacheContext) {
            return false;
        }

        //https://stackoverflow.com/questions/1545432/what-is-the-easiest-way-to-use-the-head-command-of-http-in-php?utm_medium=organic&utm_source=google_rich_qa&utm_campaign=google_rich_qa
        try {
            $context = stream_context_create(array('http' => array('method' => 'HEAD')));
            $fd = fopen($this->getUrl . $hash, 'rb', false, $context);

            $metas = stream_get_meta_data($fd);
            if (isset($metas['wrapper_data'])) {
                foreach ($metas['wrapper_data'] as $meta) {
                    if (preg_match('/^Last\-Modified: (.*)$/', $meta, $matches, PREG_OFFSET_CAPTURE)) {
                        return strtotime($matches[1][0]);
                    }
                }
            }
        } catch (Exception $e) {
            //So it's a FALSE
        }
        return false;
    }

    public function __toString()
    {
        return HttpStorage::class . " ($this->getUrl - $this->postUrl)";
    }


    /**
     * @return bool
     */
    public function clearCache()
    {
        // TODO: should probably be implemented, but how?
        return false;
    }

    /**
     * @param $hash
     * @return bool
     */
    public function remove($hash)
    {
        // TODO: should probably be implemented, but how?
        return false;
    }
}
