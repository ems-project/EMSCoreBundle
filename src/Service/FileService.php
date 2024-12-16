<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\HashMismatchException;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Processor\Processor;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CommonBundle\Storage\SizeMismatchException;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CommonBundle\Storage\StorageServiceMissingException;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\Helpers\Html\MimeTypes;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipStream\ZipStream;

class FileService implements EntityServiceInterface
{
    public function __construct(
        private readonly Registry $doctrine,
        private readonly StorageManager $storageManager,
        private readonly Processor $processor,
        private readonly UploadedAssetRepository $uploadedAssetRepository,
    ) {
    }

    /**
     * @return array<string, bool>
     */
    public function getHealthStatuses(): array
    {
        return $this->storageManager->getHealthStatuses();
    }

    public function getFile(string $hash): ?string
    {
        if (!$this->storageManager->head($hash)) {
            return null;
        }
        try {
            return $this->storageManager->getFile($hash)->getFilename();
        } catch (NotFoundException) {
            return null;
        }
    }

    public function getResource(string $hash): ?StreamInterface
    {
        try {
            return $this->storageManager->getStream($hash);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getStreamResponse(string $hash, string $disposition, Request $request): Response
    {
        if ($request->query->has('type') && $request->query->has('name')) {
            $lastUploaded = null;
        } else {
            $lastUploaded = $this->uploadedAssetRepository->getLastUploadedByHash($hash);
        }
        $config = $this->processor->configFactory($hash, [
            EmsFields::ASSET_CONFIG_MIME_TYPE => $request->query->get('type', null !== $lastUploaded ? $lastUploaded->getType() : MimeTypes::APPLICATION_OCTET_STREAM->value),
            EmsFields::ASSET_CONFIG_DISPOSITION => $disposition,
        ]);
        $filename = $request->query->get('name', null !== $lastUploaded ? $lastUploaded->getName() : 'filename');

        return $this->processor->getStreamedResponse($request, $config, $filename, true);
    }

    public function delete(UploadedAsset $uploadedAsset): void
    {
        $this->uploadedAssetRepository->remove($uploadedAsset);
    }

    /**
     * @param array<string> $ids
     */
    public function deleteByIds(array $ids): void
    {
        $uploadedAssets = $this->uploadedAssetRepository->findByIds($ids);

        foreach ($uploadedAssets as $uploadedAsset) {
            $this->uploadedAssetRepository->remove($uploadedAsset);
        }
    }

    /**
     * @param array<string> $fileIds
     *
     * @throws \Exception
     */
    public function createDownloadForMultiple(array $fileIds): StreamedResponse
    {
        $files = $this->uploadedAssetRepository->findByIds($fileIds);

        $response = new StreamedResponse(function () use ($files) {
            $zip = new ZipStream(outputName: 'archive.zip');
            $filenames = [];

            foreach ($files as $file) {
                $filename = $file->getName();
                $pathinfo = \pathinfo($filename);
                while (\in_array($filename, $filenames, true)) {
                    $filename = \sprintf('%s-%s.%s', $pathinfo['filename'], \bin2hex(\random_bytes(3)), $pathinfo['extension'] ?? '');
                }
                $filenames[] = $filename;
                try {
                    $zip->addFile($filename, $this->storageManager->getContents($file->getSha1()));
                    // TODO: this should works? $zip->addFileFromPsr7Stream($filename, $this->storageManager->getStream()Contents($file->getSha1()));
                } catch (NotFoundException) {
                }
            }

            $zip->finish();
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'archive.zip')
        );

        return $response;
    }

    /**
     * @return UploadedAsset[]|iterable
     */
    public function getImages(): iterable
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository(UploadedAsset::class);

        $qb = $repository
            ->createQueryBuilder('a')->where('a.type like :image')
            ->select('a.type, a.name, a.sha1, a.user')
            ->setParameter('image', 'image/%')
            ->groupBy('a.type, a.name, a.sha1, a.user');

        $query = $qb->getQuery();

        return $query->getResult();
    }

    /**
     * @return \Traversable<int, string|true>
     */
    public function heads(string ...$hashes): \Traversable
    {
        return $this->storageManager->heads(...$hashes);
    }

    public function uploadFile(string $name, string $type, string $filename, string $user): UploadedAsset
    {
        $hash = $this->storageManager->computeFileHash($filename);
        $size = \filesize($filename);
        if (false === $size) {
            throw new \RuntimeException(\sprintf('Can\'t get file size of %s', $filename));
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
        if (0 !== \strcasecmp($hashAlgo, $this->storageManager->getHashAlgo())) {
            throw new StorageServiceMissingException(\sprintf('Hash algorithms mismatch: %s vs. %s', $hashAlgo, $this->storageManager->getHashAlgo()));
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository(UploadedAsset::class);

        /** @var UploadedAsset|null $uploadedAsset */
        $uploadedAsset = $repository->findOneBy([
            'sha1' => $hash,
            'available' => false,
            'user' => $user,
        ]);

        if (null === $uploadedAsset) {
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
            throw new SizeMismatchException($hash, $uploadedAsset->getSize(), $size);
        }

        if ($this->head($hash)) {
            $hashSize = $this->getSize($hash);
            if ($hashSize !== $size) {
                throw new SizeMismatchException($hash, $hashSize, $size);
            }
            $uploadedAsset->setUploaded($uploadedAsset->getSize());
            $uploadedAsset->setAvailable(true);
        } else {
            $this->storageManager->initUploadFile($hash, $size, $name, $type, StorageInterface::STORAGE_USAGE_ASSET);
        }

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);

        return $uploadedAsset;
    }

    public function head(string $hash): bool
    {
        return $this->storageManager->head($hash);
    }

    /**
     * @return string[]
     */
    public function headIn(UploadedAsset $uploadedAsset): array
    {
        $headIn = $this->storageManager->headIn($uploadedAsset->getSha1());
        if (0 === \count($headIn)) {
            return $headIn;
        }

        $uploadedAsset->setHeadIn(\array_unique(\array_merge($headIn, $uploadedAsset->getHeadIn() ?? [])));
        $uploadedAsset->setHeadLast(new \DateTime());
        $uploadedAsset->setAvailable(true);
        $this->uploadedAssetRepository->update($uploadedAsset);

        return $headIn;
    }

    public function getSize(string $hash): int
    {
        try {
            return $this->storageManager->getSize($hash);
        } catch (NotFoundException) {
        }
        throw new NotFoundHttpException(\sprintf('File %s not found', $hash));
    }

    public function addChunk(string $hash, string $chunk, string $user, bool $skipShouldSkip = true): UploadedAsset
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository(UploadedAsset::class);

        $uploadedAsset = $repository->getInProgress($hash, $user);

        if (null === $uploadedAsset) {
            throw new NotFoundHttpException('Upload job not found');
        }

        $this->storageManager->addChunk($hash, $chunk, StorageInterface::STORAGE_USAGE_ASSET);
        $uploadedAsset->setUploaded($uploadedAsset->getUploaded() + \strlen($chunk));

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);

        if ($uploadedAsset->getUploaded() === $uploadedAsset->getSize()) {
            try {
                $this->storageManager->finalizeUpload($hash, $uploadedAsset->getSize(), StorageInterface::STORAGE_USAGE_ASSET);
                $uploadedAsset->setAvailable(true);
            } catch (\Throwable) {
                $em->remove($uploadedAsset);
                $em->flush($uploadedAsset);
                throw new \Exception('Was not able to finalize or confirmed the upload in at least one storage service');
            }
        }

        $em->persist($uploadedAsset);
        $em->flush($uploadedAsset);

        return $uploadedAsset;
    }

    private function saveFile(string $filename, UploadedAsset $uploadedAsset): UploadedAsset
    {
        $hash = $this->storageManager->saveFile($filename, StorageInterface::STORAGE_USAGE_ASSET);
        if ($hash != $uploadedAsset->getSha1()) {
            throw new HashMismatchException($hash, $uploadedAsset->getSha1());
        }

        $uploadedAsset->setAvailable(true);

        return $uploadedAsset;
    }

    public function synchroniseAsset(string $hash): void
    {
        $filename = $this->getFile($hash);
        if (null === $filename) {
            throw new NotFoundException($hash);
        }

        $newHash = $this->storageManager->saveFile($filename, StorageInterface::STORAGE_USAGE_BACKUP);

        \unlink($filename);

        if ($newHash !== $hash) {
            throw new HashMismatchException($hash, $newHash);
        }
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        $qb = $this->uploadedAssetRepository->makeQueryBuilder(searchValue: $searchValue);
        $qb->setFirstResult($from)->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('ua.%s', $orderField), $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    public function getEntityName(): string
    {
        return 'UploadedAsset';
    }

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
    }

    public function count(string $searchValue = '', $context = null): int
    {
        return (int) $this->uploadedAssetRepository->makeQueryBuilder(searchValue: $searchValue)
            ->select('count(ua.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param string[] $ids
     */
    public function toggleFileEntitiesVisibility(array $ids): void
    {
        foreach ($ids as $id) {
            $this->uploadedAssetRepository->toggleVisibility($id);
        }
    }

    /**
     * @param string[] $hashes
     */
    public function hideByHashes(array $hashes): int
    {
        return $this->uploadedAssetRepository->hideByHashes($hashes);
    }

    /**
     * @param string[] $hashes
     *
     * @return string[]
     */
    public function hashesToIds(array $hashes): array
    {
        return $this->uploadedAssetRepository->hashesToIds($hashes);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->uploadedAssetRepository->find($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }

    /**
     * @param mixed[] $config
     */
    public function generateImage(string $filename, array $config): StreamInterface
    {
        return $this->processor->generateLocalImage($filename, $config);
    }

    /**
     * @param mixed[] $config
     */
    public function localFileConfig(string $filename, array $config): Config
    {
        return $this->processor->localFileConfig($filename, $config);
    }

    public function saveContents(string $contents, string $filename, string $mimetype, int $usageType): string
    {
        return $this->storageManager->saveContents($contents, $filename, $mimetype, $usageType);
    }

    public function getAlgo(): string
    {
        return $this->storageManager->getHashAlgo();
    }
}
