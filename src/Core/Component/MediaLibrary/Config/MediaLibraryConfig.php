<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Config;

use EMS\CoreBundle\Core\Config\ConfigInterface;
use EMS\CoreBundle\Entity\ContentType;

class MediaLibraryConfig implements ConfigInterface
{
    public ?string $fieldPathOrder = null;
    /** @var array<mixed> */
    public array $defaultValue = [];
    /** @var array<mixed> */
    public array $searchQuery = [];
    public ?string $template = null;
    /** @var array<string, mixed> */
    public array $context = [];

    public int $searchSize = self::DEFAULT_SEARCH_SIZE;

    public const DEFAULT_SEARCH_SIZE = 100;

    public function __construct(
        private readonly string $hash,
        private readonly string $id,
        public readonly ContentType $contentType,
        public readonly string $fieldPath,
        public readonly string $fieldFolder,
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
