<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Asset;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\PropertyAccess\PropertyAccessor;
use EMS\CommonBundle\Common\Standard\Image;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Processor\Image as ProcessorImage;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Html\MimeTypes;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::ASSET_REFRESH_FILE_FIELD,
    description: 'Refresh file field and regenerate resized images base on the EMSCO_IMAGE_MAX_SIZE environment variable.',
    hidden: false
)]
class RefreshFileFieldCommand extends AbstractCommand
{
    private const string USER = 'SYSTEM_REFRESH_FILE_FIELDS';
    private User $fakeUser;

    public function __construct(private readonly RevisionService $revisionService, private readonly StorageManager $storageManager, private readonly FileService $fileService, private readonly int $imageMaxSize)
    {
        parent::__construct();
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->fakeUser = new User();
        $this->fakeUser->setUsername(self::USER);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $revisions = $this->revisionService->search([]);
        $this->io->progressStart($revisions->count());
        foreach ($revisions->getIterator() as $revision) {
            $this->refreshFileFields($revision);
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();
        $this->io->note('All revision\'s file fields have been refreshed');
        $this->io->warning('All environments should be rebuilt');

        return self::EXECUTE_SUCCESS;
    }

    private function refreshFileFields(Revision $revision): void
    {
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();
        $rawData = $revision->getRawData();
        $fieldsFound = false;
        foreach ($propertyAccessor->fileFields($revision->getRawData()) as $propertyPath => $fileField) {
            $fieldsFound = true;
            $hash = $fileField[EmsFields::CONTENT_FILE_HASH_FIELD] ?? $fileField[EmsFields::CONTENT_FILE_HASH_FIELD_] ?? null;
            $filename = $fileField[EmsFields::CONTENT_FILE_NAME_FIELD] ?? $fileField[EmsFields::CONTENT_FILE_NAME_FIELD_] ?? null;
            if (!\is_string($hash) || !\is_string($filename)) {
                continue;
            }
            $fileField[EmsFields::CONTENT_FILE_HASH_FIELD_] = $hash;
            $fileField[EmsFields::CONTENT_FILE_NAME_FIELD_] = $filename;
            $type = (string) ($fileField[EmsFields::CONTENT_MIME_TYPE_FIELD] ?? $fileField[EmsFields::CONTENT_MIME_TYPE_FIELD_] ?? MimeTypes::APPLICATION_OCTET_STREAM->value);
            $fileField[EmsFields::CONTENT_MIME_TYPE_FIELD_] = $type;
            $size = $fileField[EmsFields::CONTENT_FILE_SIZE_FIELD] ?? $fileField[EmsFields::CONTENT_FILE_SIZE_FIELD_] ?? null;
            if (null === $size) {
                unset($fileField[EmsFields::CONTENT_FILE_SIZE_FIELD_]);
            } else {
                $fileField[EmsFields::CONTENT_FILE_SIZE_FIELD_] = (int) $size;
            }
            $fileField[EmsFields::CONTENT_FILE_ALGO_FIELD_] ??= 'sha1';
            $resizedHash = $this->refreshImageField($hash, $filename, $type);
            if (null === $resizedHash) {
                unset($fileField[EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD]);
            } else {
                $fileField[EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD] = $resizedHash;
            }
            $propertyAccessor->setValue($rawData, $propertyPath, $fileField);
        }
        if (!$fieldsFound) {
            return;
        }
        $this->revisionService->lock($revision, $this->fakeUser);
        $this->revisionService->save($revision, $rawData);
    }

    private function refreshImageField(string $hash, string $filename, string $type): ?string
    {
        $extension = match ($type) {
            MimeTypes::IMAGE_PNG->value => 'png',
            MimeTypes::IMAGE_JPEG->value => 'jpeg',
            MimeTypes::IMAGE_WEBP->value => 'webp',
            default => null,
        };
        if (null === $extension) {
            return null;
        }
        $file = $this->storageManager->getFile($hash);
        $imageSize = Image::imageSize($file->getFilename());
        if ($imageSize[0] <= $this->imageMaxSize && $imageSize[1] <= $this->imageMaxSize) {
            return null;
        }
        $options = [
            EmsFields::ASSET_CONFIG_TYPE => EmsFields::ASSET_CONFIG_TYPE_IMAGE,
            EmsFields::ASSET_CONFIG_MIME_TYPE => $type,
            EmsFields::ASSET_CONFIG_QUALITY => MimeTypes::IMAGE_JPEG->value === $type ? 90 : 0,
            EmsFields::ASSET_CONFIG_IMAGE_FORMAT => $extension,
            EmsFields::ASSET_CONFIG_RESIZE => 'ratio',
            EmsFields::ASSET_CONFIG_WIDTH => $imageSize[0] > $imageSize[1] ? $this->imageMaxSize : 0,
            EmsFields::ASSET_CONFIG_HEIGHT => $imageSize[0] <= $imageSize[1] ? $this->imageMaxSize : 0,
        ];
        Json::normalize($options);
        $optionsHash = $this->storageManager->computeStringHash(Json::encode($options));
        $config = new Config($this->storageManager, $hash, $optionsHash, $options);
        $imageResizer = new ProcessorImage($config);
        $resizedImage = $imageResizer->generate($file->getFilename());
        $pathInfo = \pathinfo($filename);
        $resizedImageSize = Image::imageSize($resizedImage->getFilename());
        $resizedFileHash = $this->storageManager->computeFileHash($resizedImage->getFilename());
        if ($this->fileService->head($resizedFileHash)) {
            return $resizedFileHash;
        }
        $resizedFilename = \sprintf('%s_%dx%d.%s', $pathInfo['filename'], $resizedImageSize[0], $resizedImageSize[1], $pathInfo['extension'] ?? $extension);
        $uploadedAsset = $this->fileService->uploadFile($resizedFilename, $type, $resizedImage->getFilename(), self::USER);

        return $uploadedAsset->getSha1();
    }
}
