<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\Helpers\Standard\DateTime;

class CacheAssetExtractor
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    private string $hash;

    /** @var mixed[]|null */
    private ?array $data = null;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array<mixed> $data
     */
    public function setData(?array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
