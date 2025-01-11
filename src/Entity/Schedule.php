<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Validator\Constraints as EMSAssert;
use EMS\Helpers\Standard\DateTime;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class Schedule extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;

    private UuidInterface $id;
    protected string $name = '';
    /** @EMSAssert\Cron() */
    protected string $cron = '';
    protected ?string $command = null;
    private ?\Datetime $previousRun = null;
    private \Datetime $nextRun;
    protected int $orderKey = 0;
    protected ?string $tag = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public static function fromJson(string $json, ?\EMS\CommonBundle\Entity\EntityInterface $schedule = null): Schedule
    {
        $meta = JsonClass::fromJsonString($json);
        $schedule = $meta->jsonDeserialize($schedule);
        if (!$schedule instanceof Schedule) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $schedule;
    }

    public function __clone()
    {
        $this->id = Uuid::uuid4();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
        $this->orderKey = 0;
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    #[\Override]
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

    public function givePreviousRun(): \DateTime
    {
        if (null === $this->previousRun) {
            throw new \RuntimeException('Previous run date is null');
        }

        return $this->previousRun;
    }

    public function getPreviousRun(): ?\Datetime
    {
        return $this->previousRun;
    }

    public function setPreviousRun(?\Datetime $previousRun): void
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

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->removeProperty('previousRun');
        $json->removeProperty('nextRun');

        return $json;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): void
    {
        $this->tag = $tag;
    }
}
