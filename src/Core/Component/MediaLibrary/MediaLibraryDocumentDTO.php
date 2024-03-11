<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolder;
use EMS\CoreBundle\Validator\Constraints as EMSAssert;
use EMS\Helpers\Standard\Type;
use Symfony\Component\Validator\Constraints as Assert;

#[EMSAssert\MediaLibrary\DocumentDTO]
class MediaLibraryDocumentDTO
{
    #[Assert\NotBlank]
    public ?string $name = null;
    public ?string $id = null;

    private function __construct(
        private readonly string $folder
    ) {
    }

    public static function newFolder(MediaLibraryFolder $parentFolder = null): self
    {
        $folder = $parentFolder ? $parentFolder->getPath()->getValue().'/' : '/';

        return new self($folder);
    }

    public static function updateFolder(MediaLibraryFolder $folder): self
    {
        $dto = new self($folder->getPath()->getFolderValue());
        $dto->id = $folder->id;
        $dto->name = $folder->getName();

        return $dto;
    }

    public function getName(): string
    {
        return Type::string($this->name);
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function getPath(): string
    {
        return $this->getFolder().$this->name;
    }
}
