<?php

namespace EMS\CoreBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Form\Field\FilterOptionsType;

/**
 * Analyzer
 *
 * @ORM\Table(name="asset_storage", uniqueConstraints={@ORM\UniqueConstraint(name="asset_key_index", columns={"hash", "context"})})
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\AssetStorageRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class AssetStorage
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var string
     *
     * @ORM\Column(name="hash", type="string", length=128)
     */
    private $hash;

    /**
     * @var string
     *
     * @ORM\Column(name="context", type="string", length=128, nullable=true)
     */
    private $context;

    /**
     * @var resource
     *
     * @ORM\Column(name="contents", type="blob")
     */
    private $contents;

    /**
     * @var int
     *
     * @ORM\Column(name="last_update_date", type="integer")
     */
    private $lastUpdateDate;

    /**
     * @var int
     *
     * @ORM\Column(name="size", type="bigint")
     */
    private $size;

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
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
     * @return AssetStorage
     */
    public function setHash($hash): AssetStorage
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     *
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * @param $contents
     * @return AssetStorage
     */
    public function setContents($contents): AssetStorage
    {
        $this->contents = $contents;
        return $this;
    }


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     *
     * @return AssetStorage
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified
     *
     * @param \DateTime $modified
     *
     * @return AssetStorage
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @return integer
     */
    public function getLastUpdateDate()
    {
        return $this->lastUpdateDate;
    }

    /**
     * @param int $lastUpdateDate
     * @return AssetStorage
     */
    public function setLastUpdateDate($lastUpdateDate): AssetStorage
    {
        $this->lastUpdateDate = $lastUpdateDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param int $size
     * @return AssetStorage
     */
    public function setSize(int $size): AssetStorage
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param string $context
     * @return AssetStorage
     */
    public function setContext($context): AssetStorage
    {
        $this->context = $context;
        return $this;
    }
}
