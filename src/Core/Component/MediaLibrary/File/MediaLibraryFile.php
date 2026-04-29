<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\File;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryDocument;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MediaLibraryFile extends MediaLibraryDocument
{
    /** @var array{filename: ?string, sha1: ?string, filesize: ?int, mimetype: ?string} */
    public array $file;

    public function __construct(
        public DocumentInterface $document,
        private readonly MediaLibraryConfig $config,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($this->document, $this->config);

        $this->file = $document->getValue($config->fieldFile, [
            EmsFields::CONTENT_FILE_NAME_FIELD => null,
            EmsFields::CONTENT_FILE_HASH_FIELD => null,
            EmsFields::CONTENT_FILE_SIZE_FIELD => null,
            EmsFields::CONTENT_MIME_TYPE_FIELD => null,
        ]);
    }

    public function getFilename(): ?string
    {
        if (null === $this->name) {
            return null;
        }

        $fileInfo = \pathinfo($this->name);

        return $fileInfo['filename'] ?? null;
    }

    public function setFilename(?string $filename): void
    {
        if (null === $this->name || null === $filename) {
            return;
        }

        $extension = \pathinfo($this->name, PATHINFO_EXTENSION);

        $this->setName($extension ? $filename.'.'.$extension : $filename);
    }

    public function getFileHash(): ?string
    {
        return $this->file[EmsFields::CONTENT_FILE_HASH_FIELD] ?? null;
    }

    public function getFileResizedHash(): ?string
    {
        return $this->file[EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD] ?? null;
    }

    public function getFilesize(): ?int
    {
        return $this->file[EmsFields::CONTENT_FILE_SIZE_FIELD] ?? null;
    }

    public function getFileMimetype(): ?string
    {
        return $this->file[EmsFields::CONTENT_MIME_TYPE_FIELD] ?? null;
    }

    public function setFileHash(?string $fileHash): void
    {
        $this->file[EmsFields::CONTENT_FILE_HASH_FIELD] = $fileHash;
        $this->setFileProperty(EmsFields::CONTENT_FILE_HASH_FIELD, $fileHash);
    }

    public function setFileResizedHash(?string $fileHash): void
    {
        $this->file[EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD] = $fileHash;
        $this->setFileProperty(EmsFields::CONTENT_IMAGE_RESIZED_HASH_FIELD, $fileHash);
    }

    public function setFilesize(?int $filesize): void
    {
        $this->file[EmsFields::CONTENT_FILE_SIZE_FIELD] = $filesize;
        $this->setFileProperty(EmsFields::CONTENT_FILE_SIZE_FIELD, $filesize);
    }

    public function setFileMimetype(?string $mimetype): void
    {
        $mimetype ??= 'application/bin';

        $this->file[EmsFields::CONTENT_MIME_TYPE_FIELD] = $mimetype;
        $this->setFileProperty(EmsFields::CONTENT_MIME_TYPE_FIELD, $mimetype);
    }

    #[\Override]
    public function setName(?string $name): void
    {
        parent::setName($name);
        $this->setFileProperty(EmsFields::CONTENT_FILE_NAME_FIELD, $name);
    }

    public function urlView(): string
    {
        return $this->urlGenerator->generate('ems.file.view', [
            'sha1' => $this->getFileHash(),
            'type' => $this->getFileMimetype(),
            'name' => $this->giveName(),
        ]);
    }

    public function urlPreview(): string
    {
        return $this->urlGenerator->generate('ems_asset_processor', [
            'hash' => $this->getFileHash(),
            'processor' => 'preview',
            'type' => $this->getFileMimetype(),
            'name' => $this->giveName(),
        ]);
    }

    public function getDataJson(): string
    {
        return Json::encode([
            'filename' => $this->giveName(),
            'filesize' => $this->getFilesize(),
            'mimetype' => $this->getFileMimetype(),
            'sha1' => $this->getFileHash(),
            'preview_url' => $this->urlPreview(),
            'view_url' => $this->urlView(),
        ]);
    }

    private function setFileProperty(string $property, int|string|null $value): void
    {
        $this->document->setValue(\sprintf('%s[%s]', $this->config->fieldFile, $property), $value);
    }
}
