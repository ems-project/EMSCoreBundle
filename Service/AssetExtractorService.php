<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Common\Converter;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\CacheAssetExtractor;
use EMS\CoreBundle\Exception\AssetNotFoundException;
use EMS\CoreBundle\Tika\TikaWrapper;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class AssetExtractorService implements CacheWarmerInterface
{

    private const CONTENT_EP = '/tika';
    private const HELLO_EP = '/tika';
    private const META_EP = '/meta';
    
    /** @var ?string */
    private $tikaServer;

    /** @var string */
    private $projectDir;

    /** @var ?string */
    private $tikaDownloadUrl;
    
    /** @var RestClientService $rest*/
    private $rest;
    
    /** @var LoggerInterface */
    private $logger;

    /** @var Registry $doctrine */
    private $doctrine;

    /** @var FileService */
    private $fileService;

    /** @var ?TikaWrapper */
    private $wrapper = null;
    
    
    public function __construct(RestClientService $rest, LoggerInterface $logger, Registry $doctrine, FileService $fileService, ?string $tikaServer, string $projectDir, ?string $tikaDownloadUrl)
    {
        $this->tikaServer = $tikaServer;
        $this->projectDir = $projectDir;
        $this->rest = $rest;
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->fileService = $fileService;
        $this->tikaDownloadUrl = $tikaDownloadUrl;
    }

    private function getTikaWrapper(): TikaWrapper
    {
        if ($this->wrapper instanceof TikaWrapper) {
            return $this->wrapper;
        }

        $filename = $this->projectDir . '/var/tika-app.jar';
        if (! \file_exists($filename) && $this->tikaDownloadUrl) {
            try {
                \file_put_contents($filename, \fopen($this->tikaDownloadUrl, 'r'));
            } catch (\Throwable $e) {
                if (\file_exists($filename)) {
                    \unlink($filename);
                }
            }
        }

        if (! file_exists($filename)) {
            throw new \RuntimeException("Tika's jar not found");
        }

        $this->wrapper = new TikaWrapper($filename);
        return $this->wrapper;
    }

    /**
     * @return array{code:int,content:string}
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
            $temporaryName = \tempnam(\sys_get_temp_dir(), 'TikaWrapperTest');
            if ($temporaryName === false) {
                throw new \RuntimeException('It was possible to generate a temporary filename');
            }
            \file_put_contents($temporaryName, "elasticms's built in TikaWrapper : àêïôú");
            return [
                'code' => 200,
                'content' => $this->cleanString($this->getTikaWrapper()->getText($temporaryName)),
            ];
        }
    }

    /**
     * @return array|false|mixed
     */
    public function extractData(string $hash, string $file = null, bool $forced = false)
    {

        $manager = $this->doctrine->getManager();
        $repository = $manager->getRepository('EMSCoreBundle:CacheAssetExtractor');

        /**@var CacheAssetExtractor $cacheData*/
        $cacheData = $repository->findOneBy([
            'hash' => $hash
        ]);

        if ($cacheData instanceof CacheAssetExtractor) {
            return $cacheData->getData();
        }

        if ($file === null || !\file_exists($file)) {
            $file = $this->fileService->getFile($hash);
        }

        if (!$file || !file_exists($file)) {
            throw new AssetNotFoundException($hash);
        }

        $filesize = \filesize($file);
        if ($filesize === false) {
            throw new \RuntimeException('Not able to get asset size');
        }
        if (!$forced && filesize($file) > (3 * 1024 * 1024)) {
            $this->logger->warning('log.warning.asset_extract.file_to_large', [
                'filesize' => Converter::formatBytes($filesize),
                'max_size' => '3 MB',
            ]);
            return [];
        }

        $out = [];
        $canBePersisted = true;
        if (! empty($this->tikaServer)) {
            try {
                $client = $this->rest->getClient($this->tikaServer, $forced ? 900 : 30);
                $body = \file_get_contents($file);
                $result = $client->put(self::META_EP, [
                        'body' => $body,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                ]);
                
                $out = \json_decode($result->getBody()->__toString(), true);
                
                $result = $client->put(self::CONTENT_EP, [
                        'body' => $body,
                        'headers' => [
                                'Accept' => 'text/plain',
                        ],
                ]);
                
                $out['content'] = $result->getBody()->__toString();
            } catch (Exception $e) {
                $this->logger->error('service.asset_extractor.extract_error', [
                    'file_hash' => $hash,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'tika' => 'server',
                ]);
                $canBePersisted = false;
            }
        } else {
            try {
                $out = AssetExtractorService::convertMetaToArray($this->getTikaWrapper()->getMetadata($file));
                if (!isset($out['content'])) {
                    $text = $this->getTikaWrapper()->getText($file);
                    if (!mb_check_encoding($text)) {
                        $text = mb_convert_encoding($text, mb_internal_encoding(), 'ASCII');
                    }
                    $text = (preg_replace('/(\n)(\s*\n)+/', '${1}', $text));
                    $out['content'] =  $text;
                }
                if (!isset($out['language'])) {
                    $out['language'] = AssetExtractorService::cleanString($this->getTikaWrapper()->getLanguage($file));
                }
            } catch (Exception $e) {
                $this->logger->error('service.asset_extractor.extract_error', [
                    'file_hash' => $hash,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'tika' => 'jar',
                ]);
                $canBePersisted = false;
            }
        }

        if ($canBePersisted) {
            try {
                $cacheData = new CacheAssetExtractor();
                $cacheData->setHash($hash);
                $cacheData->setData($out);
                $manager->persist($cacheData);
                $manager->flush();
            } catch (Exception $e) {
                $this->logger->warning('service.asset_extractor.persist_error', [
                    'file_hash' => $hash,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    'tika' => 'jar',
                ]);
            }
        }
        return $out;
    }

    private static function cleanString(string $string): string
    {
        if (!\mb_check_encoding($string)) {
            $string = \mb_convert_encoding($string, mb_internal_encoding(), 'ASCII');
        }
        if (!is_string($string)) {
            throw new \RuntimeException('Unexpected issue while multi byte encoded data');
        }
        return \preg_replace('/\n|\r/', '', $string) ?? '';
    }

    /**
     * @return array<string, string>
     */
    private static function convertMetaToArray(string $data): array
    {
        if (!\mb_check_encoding($data)) {
            $data = \mb_convert_encoding($data, \mb_internal_encoding(), 'ASCII');
        }
        $cleaned = \preg_replace("/\r/", "", $data);
        if ($cleaned === null) {
            throw new \RuntimeException('It was possible to parse meta information');
        }
        $matches = [];
        \preg_match_all(
            "/^(.*): (.*)$/m",
            $cleaned,
            $matches,
            PREG_PATTERN_ORDER
        );
        $metaArray = \array_combine($matches[1], $matches[2]);
        if ($metaArray === false) {
            return [];
        }
        return $metaArray;
    }

    public function isOptional()
    {
        return false;
    }

    public function warmUp($cacheDir): void
    {
        if (empty($this->tikaServer)) {
            $this->getTikaWrapper();
        }
    }
}
