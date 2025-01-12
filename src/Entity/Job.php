<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;

class Job extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    private string $status = '';
    private ?string $output = null;
    private bool $done = false;
    private bool $started = false;
    private int $progress = 0;
    private ?string $user = null;
    /** @var string|null */
    protected $command;
    protected ?string $tag = null;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function getStarted(): bool
    {
        return $this->started;
    }

    public function setUser(string $user): Job
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): string
    {
        if (null === $this->user) {
            throw new \RuntimeException('Unexpected null user');
        }

        return $this->user;
    }

    public function setStatus(string $status): Job
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setDone(bool $done): Job
    {
        $this->done = $done;

        return $this;
    }

    public function setStarted(bool $started): Job
    {
        $this->started = $started;

        return $this;
    }

    public function getDone(): bool
    {
        return $this->done;
    }

    public function setProgress(int $progress): Job
    {
        $this->progress = $progress;

        return $this;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setOutput(string $output): Job
    {
        $this->output = $output;

        return $this;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setCommand(?string $command): Job
    {
        $this->command = $command;

        return $this;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->removeProperty('status');
        $json->removeProperty('output');
        $json->removeProperty('done');
        $json->removeProperty('started');
        $json->removeProperty('progress');
        $json->removeProperty('user');

        return $json;
    }

    public function hasTag(): bool
    {
        return null !== $this->tag;
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
