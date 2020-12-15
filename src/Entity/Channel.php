<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="channel")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
final class Channel
{
    /**
     * @var UuidInterface
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=UuidGenerator::class)
     */
    private $id;

    /**
     * @var \Datetime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \Datetime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var bool
     *
     * @ORM\Column(name="public", type="boolean", options={"default" : 0})
     */
    private $public;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255)
     */
    private $slug;

    /**
     * @var array<string, mixed>
     *
     * @ORM\Column(name="options", type="json", nullable=true)
     */
    private $options;

    /**
     * @var int
     *
     * @ORM\Column(name="order_key", type="integer")
     */
    private $orderKey;

    public function __construct()
    {
        $now = new \DateTime();

        $this->id = Uuid::uuid4();
        $this->created = $now;
        $this->modified = $now;
        $this->public = false;
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified(): void
    {
        $this->modified = new \DateTime();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getCreated(): \Datetime
    {
        return $this->created;
    }

    public function getModified(): \Datetime
    {
        return $this->modified;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOrderKey(): int
    {
        return $this->orderKey;
    }

    public function setOrderKey(int $orderKey): void
    {
        $this->orderKey = $orderKey;
    }
}
