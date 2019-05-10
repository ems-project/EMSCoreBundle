<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\CacheAssetExtractor;
use EMS\CoreBundle\Exception\AssetNotFoundException;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Tika\TikaWrapper;
use Throwable;

class AssetExtratorService
{
    
    const CONTENT_EP = '/tika';
    const HELLO_EP = '/tika';
    const META_EP = '/meta';
    
    
    /**@var string */
    private $tikaServer;

    /**@var string */
    private $projectDir;

    /**@var string */
    private $tikaDownloadUrl;
    
    /**@var RestClientService $rest*/
    private $rest;
    
    /**@var Session $session*/
    private $session;

    /**@var Registry $doctrine */
    private $doctrine;

    /**@var FileService */
    private $fileService;

    /**@var TikaWrapper */
    private $tikaWrapper;
    
    
    /**
     *
     * @param string $tikaServer
     */
    public function __construct(RestClientService $rest, Session $session, Registry $doctrine, FileService $fileService, ?string $tikaServer, string $projectDir, ?string $tikaDownloadUrl)
    {
        $this->tikaServer = $tikaServer;
        $this->projectDir = $projectDir;
        $this->rest = $rest;
        $this->session = $session;
        $this->doctrine = $doctrine;
        $this->fileService = $fileService;
        $this->tikaWrapper = null;
        $this->tikaDownloadUrl = $tikaDownloadUrl;
    }

    /**
     * @throws \Exception
     */
    private function getTikaWrapper() : ?TikaWrapper {
        if ($this->tikaWrapper === null) {

            $filename = $this->projectDir.'/var/tika-app.jar';
            if(! file_exists($filename) && $this->tikaDownloadUrl) {
                try {
                    file_put_contents($filename, fopen($this->tikaDownloadUrl, 'r'));
                }
                catch (Throwable $e) {
                    if(file_exists($filename)) {
                        unlink($filename);
                    }
                }
            }

            if(! file_exists($filename) ) {
                throw new Exception("Tika's jar not found");
            }

            $this->tikaWrapper = new TikaWrapper($filename);
        }
        return $this->tikaWrapper;
    }

    /**
     * @throws Exception
     */
    public function hello():array
    {
        if (! empty($this->tikaServer)) {
            $client = $this->rest->getClient($this->tikaServer);
            $result = $client->get(self::HELLO_EP);
            return [
                    'code' => $result->getStatusCode(),
                    'content' => $result->getBody()->__toString(),
            ];
        } else {
            $temp_file = tempnam(sys_get_temp_dir(), 'TikaWrapperTest');
            file_put_contents($temp_file, "elasticms's built in TikaWrapper : àêïôú");
            return [
                'code' => 200,
                'content' => $this->cleanString($this->getTikaWrapper()->getText($temp_file)),
            ];
        }
    }

    /**
     * @param string $hash
     * @return array|mixed
     * @throws AssetNotFoundException
     */
    public function extractData($hash, $file = null)
    {

        $manager = $this->doctrine->getManager();
        $repository = $manager->getRepository('EMSCoreBundle:CacheAssetExtractor');

        /**@var CacheAssetExtractor $cacheData*/
        $cacheData = $repository->findOneBy([
            'hash' => $hash
        ]);

        if (! empty($cacheData)) {
            return $cacheData->getData();
        }

        if (!$file || !file_exists($file)) {
            $file = $this->fileService->getFile($hash);
        }

        if (!$file || !file_exists($file)) {
            throw new AssetNotFoundException($hash);
        }

        $out = [];
        $canBePersisted = true;
        if ($this->tikaServer) {
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
            } catch (Exception $e) {
                $this->session->getFlashBag()->add('error', 'elasticms encountered an issue while extracting file data: '.$e->getMessage());
                $canBePersisted = false;
            }
        } else {
            try {
                $out = AssetExtratorService::convertMetaToArray($this->getTikaWrapper()->getMetadata($file));
                if (!isset($out['content'])) {
                    $text = $this->getTikaWrapper()->getText($file);
                    if (!mb_check_encoding($text)) {
                        $text = mb_convert_encoding($text, mb_internal_encoding(), 'ASCII');
                    }
                    $text = (preg_replace('/(\n)(\s*\n)+/', '${1}', $text));
                    $out['content'] =  $text;
                }
                if (!isset($out['language'])) {
                    $out['language'] = AssetExtratorService::cleanString($this->getTikaWrapper()->getLanguage($file));
                }
            } catch (Exception $e) {
                $this->session->getFlashBag()->add('error', 'Error with Tika: '.$e->getMessage());
                $canBePersisted = false;
            }
        }

        if ($canBePersisted) {
            try {
                $cacheData = new CacheAssetExtractor();
                $cacheData->setHash($hash);
                $cacheData->setData($out);
                $manager->persist($cacheData);
                $manager->flush($cacheData);
            } catch (Exception $e) {
                $this->session->getFlashBag()->add('warning', 'Asset extractor was not able to save in its cache: '.$e->getMessage());
            }
        }
        return $out;
    }
    
    private static function cleanString($string)
    {
        if (!mb_check_encoding($string)) {
            $string = mb_convert_encoding($string, mb_internal_encoding(), 'ASCII');
        }
        return preg_replace("/\n/", "", (preg_replace("/\r/", "", $string)));
    }
    
    private static function convertMetaToArray($data)
    {
        if (!mb_check_encoding($data)) {
            $data = mb_convert_encoding($data, mb_internal_encoding(), 'ASCII');
        }
        $cleaned = (preg_replace("/\r/", "", $data));
        $matches = [];
        preg_match_all(
            "/^(.*): (.*)$/m",
            $cleaned,
            $matches,
            PREG_PATTERN_ORDER
        );
        return array_combine($matches[1], $matches[2]);
    }
}
