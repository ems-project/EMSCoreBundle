<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\Helpers\Standard\DateTime;

/**
 * @ORM\Table(name="cache_asset_extractor")
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class CacheAssetExtractor
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(name="hash", type="string", nullable=false, unique=true)
     */
    private string $hash;

    /**
     * @ORM\Column(name="data", type="json", nullable=true)
     *
     * @var mixed[]|null
     */
    private ?array $data = null;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
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
