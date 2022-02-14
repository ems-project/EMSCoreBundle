<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;

/**
 * DataField.
 *
 * @ORM\Table(name="job")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\JobRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Job extends JsonDeserializer implements \JsonSerializable, \EMS\CommonBundle\Entity\EntityInterface
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
     * @ORM\Column(name="status", type="text", nullable=true)
     */
    private $status = '';

    /**
     * @var string
     *
     * @ORM\Column(name="output", type="text", nullable=true)
     */
    private $output;

    /**
     * @var bool
     *
     * @ORM\Column(name="done", type="boolean")
     */
    private $done = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="started", type="boolean")
     */
    private $started = false;

    /**
     * @var int
     *
     * @ORM\Column(name="progress", type="integer")
     */
    private $progress = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255, nullable=true)
     */
    private $user;

    /**
     * @var string|null
     *
     * @ORM\Column(name="command", type="string", length=2000, nullable=true)
     */
    protected $command;

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified(): void
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setCreated(\DateTime $created): Job
    {
        $this->created = $created;

        return $this;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function getStarted(): bool
    {
        return $this->started;
    }

    public function setModified(\DateTime $modified): Job
    {
        $this->modified = $modified;

        return $this;
    }

    public function getModified(): \DateTime
    {
        return $this->modified;
    }

    public function setUser(string $user): Job
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): string
    {
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

    public function jsonSerialize()
    {
        $json = new JsonClass(\get_object_vars($this), __CLASS__);
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
