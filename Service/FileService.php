<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use EMS\CommonBundle\Storage\Service\EntityStorage;
use EMS\CommonBundle\Storage\Service\FileSystemStorage;
use EMS\CommonBundle\Storage\Service\HttpStorage;
use EMS\CommonBundle\Storage\Service\S3Storage;
use EMS\CommonBundle\Storage\Service\SftpStorage;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CommonBundle\Storage\StorageServiceMissingException;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FileService
{


    /**@var Registry */
    private $doctrine;

    private $uploadFolder;
    /**@var StorageManager */
    private $storageManager;

    /**@var integer */
    private $uploadMinimuNumberOfReplications;

    public function __construct(Registry $doctrine, StorageManager $storageManager, string $projectDir, string $uploadFolder, string $storageFolder, bool $createDbStorageService, string $elasticmsRemoteServer, string $elasticmsRemoteAuthkey, string $sftpServer, string $sftpPath, string $sftpUser, string $publicKey, string $privateKey, array $s3Credentials = null, string $s3Bucket = null)
    {
        $this->doctrine = $doctrine;
        $this->uploadFolder = $uploadFolder;
        $this->storageManager = $storageManager;
        $this->uploadMinimuNumberOfReplications = 10;

        if ($storageFolder && !empty($storageFolder)) {
            if (substr($storageFolder, 0, 2) === ('.' . DIRECTORY_SEPARATOR)) {
                $this->addStorageService(new FileSystemStorage($projectDir . substr($storageFolder, 1)));
            } else {
                $this->addStorageService(new FileSystemStorage($storageFolder));
            }
        }

        if (!empty($s3Credentials) && !empty($s3Bucket)) {
            $this->addStorageService(new S3Storage($s3Credentials, $s3Bucket));
        }


        if ($createDbStorageService) {
            $this->addStorageService(new EntityStorage($doctrine, true));
        }

        if (!empty($elasticmsRemoteServer)) {
            $this->addStorageService(new HttpStorage($elasticmsRemoteServer, '/data/file/view/', $elasticmsRemoteAuthkey));
        }

        if (!empty($sftpServer) && !empty($sftpPath) && !empty($sftpPath) && !empty($publicKey) && !empty($privateKey)) {
            $this->addStorageService(new SftpStorage($sftpServer, $sftpPath, $sftpUser, $publicKey, $privateKey, true));
        }
    }

    public function addStorageService($storageAdapter)
    {
        $this->storageManager->addAdapter($storageAdapter);
    }


    public function getStorageService(string $storageServiceId): ?StorageInterface
    {
        foreach ($this->storageManager->getAdapters() as $storageService) {
            if ($storageService->__toString() == $storageServiceId) {
                return $storageService;
            }
        };
        return null;
    }

    /**
     * @return StorageInterface[]|iterable
     */
    public function getStorages()
    {
        return $this->storageManager->getAdapters();
    }

    public function getBase64($hash, $cacheContext = false)
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            $resource = $service->read($hash, $cacheContext);
            if ($resource) {
                $data = stream_get_contents($resource);
                $base64 = base64_encode($data);
                return $base64;
            }
        }
        return false;
    }

    /**
     * @param string $hash
     * @param bool $cacheContext
     * @return bool|string
     */
    public function getFile($hash, $cacheContext = false)
    {
        //TODO: instead of always to make a new copy, copy it once in the symfony cache folder
        $resource = $this->getResource($hash, $cacheContext);
        if ($resource) {
            $filename = tempnam(sys_get_temp_dir(), 'EMS');
            file_put_contents($filename, $resource);
            return $filename;
        }
        return false;
    }

    public function getResource($hash, $cacheContext = false)
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            $resource = $service->read($hash, $cacheContext);
            if ($resource) {
                return $resource;
            }
        }
        return false;
    }

    /**
     * @param string $hash
     * @param string|null $context
     *
     * @return null|\DateTime
     */
    public function getLastUpdateDate(string $hash, ?string $context = null): ?\DateTime
    {
        $out = null;
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            $date = $service->getLastUpdateDate($hash, $context);
            if ($date && ($out === null || $date < $out)) {
                $out = $date;
            }
        }
        return $out;
    }

    public function getImages()
    {

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');


        $qb = $repository
            ->createQueryBuilder('a')->where('a.type like :image')
            ->select('a.type, a.name, a.sha1, a.user')
            ->setParameter('image', 'image/%')
            ->groupBy('a.type, a.name, a.sha1, a.user');

        $query = $qb->getQuery();


        $result = $query->getResult();
        return $result;
    }

    public function uploadFile($name, $type, $filename, $user)
    {

        $hash = $this->storageManager->computeFileHash($filename);
        $size = filesize($filename);
        $uploadedAsset = $this->initUploadFile($hash, $size, $name, $type, $user, $this->storageManager->getHashAlgo());
        if (!$uploadedAsset->getAvailable()) {
            $uploadedAsset = $this->saveFile($filename, $uploadedAsset);
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);

        return $uploadedAsset;
    }

    /**
     * @param string $hash
     * @param integer $size
     * @param string $name
     * @param string $type
     * @param string $user
     * @param string $hashAlgo
     * @return UploadedAsset
     * @throws Conflict409Exception
     * @throws StorageServiceMissingException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function initUploadFile($hash, $size, $name, $type, $user, $hashAlgo)
    {
        if (empty($this->storageManager->getAdapters())) {
            throw new StorageServiceMissingException("No storage service have been defined");
        }

        if (strcasecmp($hashAlgo, $this->storageManager->getHashAlgo()) !== 0) {
            throw new StorageServiceMissingException(sprintf("Hash algorithms mismatch: %s vs. %s", $hashAlgo, $this->storageManager->getHashAlgo()));
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');


        /** @var UploadedAsset|null $uploadedAsset */
        $uploadedAsset = $repository->findOneBy([
            'sha1' => $hash,
            'available' => false,
            'user' => $user,
        ]);

        if ($uploadedAsset === null) {
            $uploadedAsset = new UploadedAsset();
            $uploadedAsset->setSha1($hash);
            $uploadedAsset->setUser($user);
            $uploadedAsset->setSize($size);
            $uploadedAsset->setHashAlgo($hashAlgo);
            $uploadedAsset->setUploaded(0);
        }


        $uploadedAsset->setType($type);
        $uploadedAsset->setName($name);
        $uploadedAsset->setAvailable(false);

        if ($size >= $uploadedAsset->getUploaded()) {
            $uploadedAsset->setUploaded(0);
        }

        if ($uploadedAsset->getSize() != $size) {
            throw new Conflict409Exception("Target size mismatched " . $uploadedAsset->getSize() . ' ' . $size);
        }

        if ($this->head($hash)) {
            if ($this->getSize($hash) != $size) { //one is a string the other is a number
                throw new Conflict409Exception("Hash conflict");
            }
            $uploadedAsset->setUploaded($uploadedAsset->getSize());
            $uploadedAsset->setAvailable(true);
        } else {
            $this->storageManager->initUploadFile($hash, $size, $name, $type, $this->uploadMinimuNumberOfReplications);
        }

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);

        return $uploadedAsset;
    }


    public function head($hash, $cacheContext = false)
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            if ($service->head($hash, $cacheContext)) {
                return true;
            }
        }
        return false;
    }

    public function getSize($hash, $cacheContext = false)
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            $filesize = $service->getSize($hash, $cacheContext);
            if ($filesize !== false) {
                return $filesize;
            }
        }
        return false;
    }

    public function addChunk($hash, $chunk, $user)
    {
        if (empty($this->storageManager->getAdapters())) {
            throw new StorageServiceMissingException("No storage service have been defined");
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');


        /**@var UploadedAsset $uploadedAsset */
        $uploadedAsset = $repository->findOneBy([
            'sha1' => $hash,
            'available' => false,
            'user' => $user,
        ]);

        if (!$uploadedAsset) {
            throw new NotFoundHttpException('Upload job not found');
        }


        $loopCounter = 0;
        foreach ($this->storageManager->getAdapters() as $service) {
            if ($service->addChunk($hash, $chunk) && ++$loopCounter >= $this->uploadMinimuNumberOfReplications) {
                break;
            }
        }

        $uploadedAsset->setUploaded($uploadedAsset->getUploaded() + strlen($chunk));

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);

        if ($uploadedAsset->getUploaded() == $uploadedAsset->getSize()) {
            $loopCounter = 0;
            foreach ($this->storageManager->getAdapters() as $service) {
                $handler = $service->read($uploadedAsset->getSha1(), false, false);

                if ($handler) {
                    $computedHash = $this->storageManager->computeStringHash($handler->getContents());

                    if ($computedHash != $uploadedAsset->getSha1()) {
                        throw new Conflict409Exception("Hash mismatched " . $computedHash . ' ' . $uploadedAsset->getSha1());
                    }

                    if ($service->finalizeUpload($hash) && ++$loopCounter >= $this->uploadMinimuNumberOfReplications) {
                        break;
                    }
                }
            }

            if ($loopCounter === 0) {
                $em->remove($uploadedAsset);
                $em->flush($uploadedAsset);
                throw new Exception('Was not able to finalize or confirmed the upload in at least one storage service');
            }
        }

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);
        return $uploadedAsset;
    }

    public function create($hash, $fileName, $cacheContext = false)
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            if ($service->create($hash, $fileName, $cacheContext)) {
                unlink($fileName);
                return true;
            }
        }
        return false;
    }

    /**
     * return temporary filename
     * @param string $hash
     * @return string
     */
    public function temporaryFilename($hash)
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $hash;
    }

    private function saveFile($filename, UploadedAsset $uploadedAsset)
    {
        $hash = $this->storageManager->computeFileHash($filename);
        if ($hash != $uploadedAsset->getSha1()) {
//          throw new Conflict409Exception("Hash mismatched ".$hash.' >< '.$uploadedAsset->getSha1());
//TODO: fix this issue by using the CryotJS librairy on the FE JS?
            $uploadedAsset->setSha1($hash);
            $uploadedAsset->setUploaded(filesize($filename));
        }

        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            if ($service->create($uploadedAsset->getSha1(), $filename)) {
                $uploadedAsset->setAvailable(true);
                unlink($filename);
                break;
            }
        }

        return $uploadedAsset;
    }
}
