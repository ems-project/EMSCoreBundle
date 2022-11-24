<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;

/**
 * DataField.
 *
 * @ORM\Table(name="job")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\JobRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Job extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(name="status", type="text", nullable=true)
     */
    private string $status = '';

    /**
     * @ORM\Column(name="output", type="text", nullable=true)
     */
    private ?string $output = null;

    /**
     * @ORM\Column(name="done", type="boolean")
     */
    private bool $done = false;

    /**
     * @ORM\Column(name="started", type="boolean")
     */
    private bool $started = false;

    /**
     * @ORM\Column(name="progress", type="integer")
     */
    private int $progress = 0;

    /**
     * @ORM\Column(name="username", type="string", length=255, nullable=true)
     */
    private ?string $user = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="command", type="string", length=2000, nullable=true)
     */
    protected $command;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function getId(): int
    {
        return $this->id;
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
}
