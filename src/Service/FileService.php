<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Processor\Processor;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CommonBundle\Storage\StorageServiceMissingException;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use Exception;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FileService
{
    /** @var Registry */
    private $doctrine;
    /** @var StorageManager */
    private $storageManager;
    /** @var Processor */
    private $processor;

    public function __construct(Registry $doctrine, StorageManager $storageManager, Processor $processor)
    {
        $this->doctrine = $doctrine;
        $this->storageManager = $storageManager;
        $this->processor = $processor;
    }

    public function getBase64(string $hash): ?string
    {
        return $this->storageManager->getBase64($hash);
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
        try {
            return $this->storageManager->getStream($hash);
        } catch (\Throwable $e) {
            return null;
        }
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
            if ($this->getSize($hash) !== $size) {
                throw new Conflict409Exception("Hash conflict");
            }
            $uploadedAsset->setUploaded($uploadedAsset->getSize());
            $uploadedAsset->setAvailable(true);
        } else {
            $this->storageManager->initUploadFile($hash, $size, $name, $type);
        }

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);

        return $uploadedAsset;
    }


    public function head(string $hash): bool
    {
        return $this->storageManager->head($hash);
    }

    public function getSize(string $hash): int
    {
        try {
            return $this->storageManager->getSize($hash);
        } catch (NotFoundException $e) {
        }
        throw new NotFoundHttpException(sprintf('File %s not found', $hash));
    }

    public function addChunk(string $hash, string $chunk, string $user, bool $skipShouldSkip): UploadedAsset
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');


        $uploadedAsset = $repository->getInProgress($hash, $user);

        if ($uploadedAsset === null) {
            throw new NotFoundHttpException('Upload job not found');
        }

        $this->storageManager->addChunk($hash, $chunk, $skipShouldSkip);
        $uploadedAsset->setUploaded($uploadedAsset->getUploaded() + \strlen($chunk));

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);

        if ($uploadedAsset->getUploaded() === $uploadedAsset->getSize()) {
            try {
                $this->storageManager->finalizeUpload($hash, $uploadedAsset->getSize(), $skipShouldSkip);
                $uploadedAsset->setAvailable(true);
            } catch (\Throwable $e) {
                $em->remove($uploadedAsset);
                $em->flush($uploadedAsset);
                throw new Exception('Was not able to finalize or confirmed the upload in at least one storage service');
            }
        }

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);
        return $uploadedAsset;
    }

    public function temporaryFilename(string $hash): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $hash;
    }

    private function saveFile(string $filename, UploadedAsset $uploadedAsset): UploadedAsset
    {
        $hash = $this->storageManager->saveFile($filename);
        if ($hash != $uploadedAsset->getSha1()) {
            throw new Conflict409Exception(sprintf('Hash mismatched %s >< %s', $hash, $uploadedAsset->getSha1()));
        }

        $uploadedAsset->setAvailable(true);
        return $uploadedAsset;
    }
}
