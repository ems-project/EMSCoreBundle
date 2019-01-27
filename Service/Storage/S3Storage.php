<?php

namespace EMS\CoreBundle\Service\Storage;

use Aws\Result;
use Aws\S3\S3Client;
use AwsServiceBuilder;
use Exception;

class S3Storage implements StorageInterface
{

    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var string|null
     */
    private $bucket;


//    /**
//     * @var string|null
//     */
//    private $region;

    /**
     * @var array|null
     */
    private $credentials;

    public function __construct($s3Credentials, $s3Bucket)
    {
        $this->bucket = $s3Bucket;
        $this->credentials = $s3Credentials;

        $this->s3Client = null;

    }

    private function init($withHealthCheck=true)
    {
        if($this->s3Client === null)
        {

            $this->s3Client = new S3Client($this->credentials);

            if($withHealthCheck && !$this->health()){
                $this->s3Client->createBucket([
                    'Bucket' => $this->bucket,
                ]);

                $this->s3Client->waitUntil('BucketExists', ['Bucket' => $this->bucket]);
            }
            $this->s3Client->registerStreamWrapper();
        }
    }

    /**
     * @inheritdoc
     */
    public function head($hash, $cacheContext = false)
    {
        $this->init();

        return $this->s3Client->doesObjectExist($this->bucket, $hash);
    }


    /**
     * @inheritdoc
     * @return bool|void
     */
    public function supportCacheStore()
    {
        $this->init();
        return true;
    }

    private function getKey($hash, $context)
    {
        return $context?$context.'/'.$hash:$hash;
    }

    /**
     * @inheritdoc
     * @return bool|void
     */
    public function remove($hash)
    {
        $this->init();
        // TODO: Implement clearCache() method.
    }

    /**
     * @inheritdoc
     */
    public function read($hash, $context = false)
    {
        $this->init();

        if(!$this->head($hash, $context)) {
            return false;
        }
        $key = $this->getKey($hash, $context);

        return fopen("s3://$this->bucket/$key", 'rb');

//        $filename = $this->getCacheFilename($hash, $context);
//
//        $result = $this->s3Client->getObject(array(
//            'Bucket' => $this->bucket,
////            'LocationConstraint' => $this->region,
//            'Key'    => $context?$context.'/'.$hash:$hash,
////            'SaveAs' => $filename
//        ));
//
////        echo($filename);
//        return $result['Body'];
    }

    /**
     * @inheritdoc
     */
    public function create($hash, $filename, $context=null)
    {

        $this->init();
        $time = @filemtime($filename);

        $this->s3Client->putObject(array(
            'Bucket'     => $this->bucket,
//            'LocationConstraint' => $this->region,
            'Key'        => $context?$context.'/'.$hash:$hash,
            'SourceFile' => $filename,
            'Metadata'   => array(
                'LastUpdateDate' => $time,
            )
        ));


        return $this->read($hash, $context);
    }

    /**
     * @inheritdoc
     */
    public function health(): bool
    {
        $this->init(false);
        try {
            $this->s3Client->headBucket([
                'Bucket' => $this->bucket,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function clearCache()
    {
        $this->init();
        // TODO: Implement clearCache() method.
    }

    /**
     * @inheritdoc
     */
    public function getLastUpdateDate($hash, $context = false)
    {
        $this->init();
        $result =  $this->s3Client->headObject([
            'Bucket'     => $this->bucket,
            'Key'        => $context?$context.'/'.$hash:$hash,
        ]);

        if(isset($result['Metadata']['LastUpdateDate'])){

            return \DateTime::createFromFormat('U', $result['Metadata']['LastUpdateDate']);
        }
        return null;
    }



    /**
     * @inheritdoc
     */
    public function getSize($hash, $context = false)
    {
        $this->init();
        $result =  $this->s3Client->headObject([
            'Bucket'     => $this->bucket,
            'Key'        => $context?$context.'/'.$hash:$hash,
        ]);

        if(isset($result['ContentLength'])){

            return $result['ContentLength'];
        }
        return null;
    }


    public function __toString()
    {
        return S3Storage::class . " ($this->bucket)";
    }

    /**
     * @param string      $hash
     * @param string|null $context
     *
     * @return string
     */
    private function getCacheFilename(string $hash, ?string $context = null): string
    {
        return $this->getCacheDir($hash, $context).'/'.$hash;
    }

    /**
     * @param string      $hash
     * @param string|null $context
     *
     * @return string
     */
    private function getCacheDir($hash, ?string $context = null): string
    {
        $dir = substr($hash, 0, 3);
        $out = $context ? sprintf('%s%s/%s', sys_get_temp_dir(), $context, $dir) : sys_get_temp_dir().$dir;


        if (!file_exists($out)) {
            mkdir($out, 0777, true);
        }

        return $out;
    }
}