<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;

class CacheAssetExtractor
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    private string $hash;

    /** @var mixed[]|null */
    private ?array $data = null;

    public function __construct()
    {
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
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
