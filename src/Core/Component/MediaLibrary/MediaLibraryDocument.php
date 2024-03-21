<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Validator\Constraints as EMSAssert;
use EMS\Helpers\Standard\Type;
use Symfony\Component\Validator\Constraints as Assert;

#[EMSAssert\MediaLibrary\Document]
class MediaLibraryDocument
{
    public string $emsId;
    public string $id;
    #[Assert\NotBlank]
    public ?string $name = null;
    public string $folder;
    public string $path;

    public function __construct(
        public DocumentInterface $document,
        private readonly MediaLibraryConfig $config,
    ) {
        $this->id = $this->document->getId();
        $this->emsId = (string) $document->getEmsLink();
        $this->folder = $this->document->getValue($this->config->fieldFolder);

        if ($path = $this->document->getValue($this->config->fieldPath)) {
            $this->path = $path;
            $this->name = $this->getPath()->getName();
        }
    }

    public function hasName(): bool
    {
        return null !== $this->name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function giveName(): string
    {
        return Type::string($this->name);
    }

    public function getPath(): MediaLibraryPath
    {
        return MediaLibraryPath::fromString($this->path);
    }

    public function setName(?string $name): void
    {
        if ($name) {
            $this->setPath(MediaLibraryPath::fromString($this->folder.$name));
        }
    }

    public function setPath(MediaLibraryPath $path): void
    {
        $this->name = $path->getName();
        $this->path = $path->getValue();

        $this->document
            ->setValue($this->config->fieldPath, $path->getValue())
            ->setValue($this->config->fieldFolder, $path->getFolderValue());
    }
}
