<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="schedule")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Schedule implements EntityInterface
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
     * @var string
     *
     * @ORM\Column(name="cron", type="string", length=255)
     */
    private $cron;

    /**
     * @var string|null
     *
     * @ORM\Column(name="command", type="string", length=2000, nullable=true)
     */
    private $command;

    /**
     * @var \Datetime
     *
     * @ORM\Column(name="previous_run", type="datetime", nullable=true)
     */
    private $previousRun;

    /**
     * @var \Datetime
     *
     * @ORM\Column(name="next_run", type="datetime")
     */
    private $nextRun;

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

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function setCreated(\DateTime $created): void
    {
        $this->created = $created;
    }

    public function getModified(): \DateTime
    {
        return $this->modified;
    }

    public function setModified(\DateTime $modified): void
    {
        $this->modified = $modified;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCron(): string
    {
        return $this->cron;
    }

    public function setCron(string $cron): void
    {
        $this->cron = $cron;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }

    public function setCommand(?string $command): void
    {
        $this->command = $command;
    }

    public function getPreviousRun(): \Datetime
    {
        return $this->previousRun;
    }

    public function setPreviousRun(\Datetime $previousRun): void
    {
        $this->previousRun = $previousRun;
    }

    public function getNextRun(): \Datetime
    {
        return $this->nextRun;
    }

    public function setNextRun(\Datetime $nextRun): void
    {
        $this->nextRun = $nextRun;
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
