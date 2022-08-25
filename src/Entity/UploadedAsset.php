<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\Helpers\Standard\DateTime;

/**
 * DataField.
 *
 * @ORM\Table(name="uploaded_asset")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\UploadedAssetRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class UploadedAsset implements EntityInterface
{
    use CreatedModifiedTrait;
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=64, nullable=true)
     */
    private $status;

    /**
     * @var string
     *
     * @ORM\Column(name="sha1", type="string", length=128)
     */
    private $sha1;

    /**
     * @ORM\Column(name="name", type="string", length=1024)
     */
    private string $name = '';

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=1024)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255)
     */
    private $user;

    /**
     * @var bool
     *
     * @ORM\Column(name="available", type="boolean")
     */
    private $available;

    /**
     * @var int|string
     *
     * @ORM\Column(name="size", type="bigint")
     */
    private $size;

    /**
     * @var int|string
     *
     * @ORM\Column(name="uploaded", type="bigint")
     */
    private $uploaded;

    /**
     * @var string
     *
     * @ORM\Column(name="hash_algo", type="string", length=32, options={"default" : "sha1"})
     */
    private $hashAlgo;

    /**
     * @ORM\Column(name="hidden", type="boolean", options={"default" : 0})
     */
    private bool $hidden = false;

    /**
     * @ORM\Column(name="head_last", type="datetime", nullable=true)
     */
    private ?\DateTime $headLast;

    /**
     * @var string[]|null
     *
     * @ORM\Column(name="head_in", type="array", nullable=true)
     */
    private ?array $headIn;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    /**
     * @return array{sha1:string, type:string, available:bool, name:string, size:int, status:string, uploaded:int, user:string}
     */
    public function getResponse(): array
    {
        return [
            'sha1' => $this->getSha1(),
            'type' => $this->getType(),
            'available' => $this->getAvailable(),
            'name' => $this->getName(),
            'size' => $this->getSize(),
            'status' => $this->getStatus(),
            'uploaded' => $this->getUploaded(),
            'user' => $this->getUser(),
        ];
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set status.
     *
     * @param string $status
     *
     * @return UploadedAsset
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return UploadedAsset
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set type.
     *
     * @param string $type
     *
     * @return UploadedAsset
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set user.
     *
     * @param string $user
     *
     * @return UploadedAsset
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set available.
     *
     * @param bool $available
     *
     * @return UploadedAsset
     */
    public function setAvailable($available)
    {
        $this->available = $available;

        return $this;
    }

    /**
     * Get available.
     *
     * @return bool
     */
    public function getAvailable()
    {
        return $this->available;
    }

    public function setSize(int $size): UploadedAsset
    {
        $this->size = $size;

        return $this;
    }

    public function getSize(): int
    {
        return \intval($this->size);
    }

    public function setUploaded(int $uploaded): UploadedAsset
    {
        $this->uploaded = $uploaded;

        return $this;
    }

    public function getUploaded(): int
    {
        return \intval($this->uploaded);
    }

    /**
     * Set sha1.
     *
     * @param string $sha1
     *
     * @return UploadedAsset
     */
    public function setSha1($sha1)
    {
        $this->sha1 = $sha1;

        return $this;
    }

    /**
     * Get sha1.
     *
     * @return string
     */
    public function getSha1()
    {
        return $this->sha1;
    }

    /**
     * @return array{filename:string,filesize:int,mimetype:string,sha1:string,_hash_algo:string}
     */
    public function getData(): array
    {
        return [
            EmsFields::CONTENT_FILE_NAME_FIELD => $this->getName(),
            EmsFields::CONTENT_FILE_SIZE_FIELD => $this->getSize(),
            EmsFields::CONTENT_MIME_TYPE_FIELD => $this->getType(),
            EmsFields::CONTENT_FILE_HASH_FIELD => $this->getSha1(),
            EmsFields::CONTENT_HASH_ALGO_FIELD => $this->getHashAlgo(),
        ];
    }

    public function getHashAlgo(): string
    {
        return $this->hashAlgo;
    }

    public function setHashAlgo(string $hashAlgo): UploadedAsset
    {
        $this->hashAlgo = $hashAlgo;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): UploadedAsset
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function getHeadLast(): ?\DateTime
    {
        return $this->headLast;
    }

    public function setHeadLast(?\DateTime $headLast): UploadedAsset
    {
        $this->headLast = $headLast;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getHeadIn(): ?array
    {
        return $this->headIn;
    }

    /**
     * @param string[]|null $headIn
     */
    public function setHeadIn(?array $headIn): UploadedAsset
    {
        $this->headIn = $headIn;

        return $this;
    }
}
