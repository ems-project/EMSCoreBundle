<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;

class MediaLibraryDocument
{
    public string $emsId;
    public string $id;
    public string $path;

    public function __construct(
        public DocumentInterface $document,
        private readonly MediaLibraryConfig $config,
    ) {
        $this->id = $this->document->getId();
        $this->emsId = (string) $document->getEmsLink();
        $this->path = $document->getValue($config->fieldPath);
    }

    public function getName(): string
    {
        return $this->getPath()->getName();
    }

    public function getPath(): MediaLibraryPath
    {
        return MediaLibraryPath::fromString($this->path);
    }

    public function setName(string $name): void
    {
        $this->setPath($this->getPath()->setName($name));
    }

    public function setPath(MediaLibraryPath $path): void
    {
        $this->path = $path->getValue();

        $this->document
            ->setValue($this->config->fieldPath, $path->getValue())
            ->setValue($this->config->fieldFolder, $path->getFolderValue());
    }
}
