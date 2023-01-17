<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use EMS\CoreBundle\Core\Config\ConfigInterface;
use EMS\CoreBundle\Entity\ContentType;

class MediaLibraryConfig implements ConfigInterface
{
    public ?string $fieldPathOrder = null;
    /** @var array<mixed> */
    public array $defaultValue = [];
    /** @var array<mixed> */
    public array $searchQuery = [];

    public function __construct(
        private readonly string $hash,
        private readonly string $id,
        public readonly ContentType $contentType,
        public readonly string $fieldPath,
        public readonly string $fieldLocation,
        public readonly string $fieldFile
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHash(): string
    {
        return $this->hash;
    }
}
