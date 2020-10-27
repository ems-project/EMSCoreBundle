<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\Processor\Processor;
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
use http\Exception\RuntimeException;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    /**@var Processor */
    private $processor;

    /**
     * @param array{version?:string,credentials?:array{key:string,secret:string},region?:string}|null $s3Credentials
     */
    public function __construct(Registry $doctrine, StorageManager $storageManager, Processor $processor, string $projectDir, string $uploadFolder, string $storageFolder, bool $createDbStorageService, string $elasticmsRemoteServer, string $elasticmsRemoteAuthkey, string $sftpServer, string $sftpPath, string $sftpUser, string $publicKey, string $privateKey, array $s3Credentials = null, string $s3Bucket = null)
    {
        $this->doctrine = $doctrine;
        $this->uploadFolder = $uploadFolder;
        $this->storageManager = $storageManager;
        $this->processor = $processor;
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
            $this->addStorageService(new EntityStorage($doctrine));
        }

        if (!empty($elasticmsRemoteServer)) {
            $this->addStorageService(new HttpStorage($elasticmsRemoteServer, '/data/file/view/', $elasticmsRemoteAuthkey));
        }

        if (!empty($sftpServer) && !empty($sftpPath) && !empty($sftpPath) && !empty($publicKey) && !empty($privateKey)) {
            $this->addStorageService(new SftpStorage($sftpServer, $sftpPath, $sftpUser, $publicKey, $privateKey, true));
        }
    }

    public function addStorageService(StorageInterface $storageAdapter): void
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

    public function getBase64(string $hash): ?string
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            try {
                $stream = $service->read($hash);
            } catch (NotFoundHttpException $e) {
                continue;
            }

            return \base64_encode($stream->getContents());
        }
        return null;
    }

    public function getFile(string $hash): ?string
    {
        $filename = \sprintf('%s%sEMS_cached_%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, $hash);
        if (\file_exists($filename) && $this->storageManager->computeFileHash($filename) === $hash) {
            return $filename;
        }
        $stream = $this->getResource($hash);

        if ($stream === null) {
            return null;
        }

        if (!$handle = \fopen($filename, 'w')) {
            throw new \RuntimeException(sprintf('Can\'t open a temporary file %s', $filename));
        }

        while (!$stream->eof()) {
            if (\fwrite($handle, $stream->read(8192)) === false) {
                throw new \RuntimeException(sprintf('Can\'t write in temporary file %s', $filename));
            }
        }

        if (\fclose($handle) === false) {
            throw new \RuntimeException(sprintf('Can\'t close the temporary file %s', $filename));
        }
        return $filename;
    }

    public function getResource(string $hash): ?StreamInterface
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            try {
                return $service->read($hash);
            } catch (NotFoundHttpException $e) {
            }
        }
        return null;
    }

    public function getStreamResponse(string $sha1, string $disposition, Request $request): Response
    {
        $config = $this->processor->configFactory($sha1, [
            EmsFields::ASSET_CONFIG_MIME_TYPE => $request->query->get('type', 'application/octet-stream'),
            EmsFields::ASSET_CONFIG_DISPOSITION => $disposition
        ]);
        $filename = $request->query->get('name', 'filename');
        return $this->processor->getStreamedResponse($request, $config, $filename, true);
    }

    /**
     * @return UploadedAsset[]|iterable
     */
    public function getImages():iterable
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
        return $query->getResult();
    }

    public function uploadFile(string $name, string $type, string $filename, string $user): UploadedAsset
    {

        $hash = $this->storageManager->computeFileHash($filename);
        $size = \filesize($filename);
        if ($size === false) {
            throw new \RuntimeException(sprintf('Can\'t get file size of %s', $filename));
        }
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

    public function initUploadFile(string $hash, int $size, string $name, string $type, string $user, string $hashAlgo): UploadedAsset
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


    public function head(string $hash): bool
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            if ($service->head($hash)) {
                return true;
            }
        }
        return false;
    }

    public function getSize(string $hash): int
    {
        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            try {
                return $service->getSize($hash);
            } catch (NotFoundHttpException $e) {
            }
        }
        throw new NotFoundHttpException(sprintf('File %s not found', $hash));
    }

    public function addChunk(string $hash, string $chunk, string $user): UploadedAsset
    {
        if (empty($this->storageManager->getAdapters())) {
            throw new StorageServiceMissingException("No storage service have been defined");
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');


        $uploadedAsset = $repository->getInProgress($hash, $user);

        if ($uploadedAsset === null) {
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
                try {
                    $handler = $service->read($uploadedAsset->getSha1(), false);
                } catch (NotFoundHttpException $e) {
                    continue;
                }

                $computedHash = $this->storageManager->computeStringHash($handler->getContents());

                if ($computedHash != $uploadedAsset->getSha1()) {
                    throw new Conflict409Exception("Hash mismatched " . $computedHash . ' ' . $uploadedAsset->getSha1());
                }

                if ($service->finalizeUpload($hash) && ++$loopCounter >= $this->uploadMinimuNumberOfReplications) {
                    break;
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

    public function create(string $hash, string $fileName): bool
    {
        foreach ($this->storageManager->getAdapters() as $service) {
            if ($service->create($hash, $fileName)) {
                \unlink($fileName);
                return true;
            }
        }
        return false;
    }

    public function temporaryFilename(string $hash): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $hash;
    }

    private function saveFile(string $filename, UploadedAsset $uploadedAsset): UploadedAsset
    {
        $hash = $this->storageManager->computeFileHash($filename);
        if ($hash != $uploadedAsset->getSha1()) {
            throw new Conflict409Exception(sprintf('Hash mismatched %s >< %s', $hash, $uploadedAsset->getSha1()));
        }

        /**@var StorageInterface $service */
        foreach ($this->storageManager->getAdapters() as $service) {
            if ($service->create($uploadedAsset->getSha1(), $filename)) {
                $uploadedAsset->setAvailable(true);
                \unlink($filename);
                break;
            }
        }

        return $uploadedAsset;
    }
}
