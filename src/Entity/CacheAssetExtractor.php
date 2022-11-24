<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\Helpers\Standard\DateTime;

/**
 * Revision.
 *
 * @ORM\Table(name="cache_asset_extractor")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class CacheAssetExtractor
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
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

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return CacheAssetExtractor
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     *
     * @return CacheAssetExtractor
     */
    public function setHash($hash)
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * @return ?array<mixed>
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param ?array<mixed> $data
     *
     * @return CacheAssetExtractor
     */
    public function setData(?array $data)
    {
        $this->data = $data;

        return $this;
    }
}
