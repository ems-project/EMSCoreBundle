<?php

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\Helpers\Standard\DateTime;

class UploadedAsset implements EntityInterface
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    private ?string $status = null;
    private string $sha1;
    private string $name = '';
    private string $type;
    private string $user;
    private bool $available;
    private ?string $size = null;
    private ?string $uploaded = null;
    private ?string $hashAlgo = null;
    private bool $hidden = false;
    private ?\DateTime $headLast = null;
    /** @var ?string[] */
    private ?array $headIn = null;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    /**
     * @return array{sha1:string, type:string, available:bool, name:string, size:int, status: ?string, uploaded:int, user:string}
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

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
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

    #[\Override]
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
        $this->size = (string) $size;

        return $this;
    }

    public function getSize(): int
    {
        return (int) $this->size;
    }

    public function setUploaded(int $uploaded): UploadedAsset
    {
        $this->uploaded = (string) $uploaded;

        return $this;
    }

    public function getUploaded(): int
    {
        return (int) $this->uploaded;
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
        if (null === $this->hashAlgo) {
            throw new \RuntimeException('Unexpected null hash algo');
        }

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
