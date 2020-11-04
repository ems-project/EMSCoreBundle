<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DataField
 *
 * @ORM\Table(name="job")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\JobRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Job
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
    private $status;

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
    private $done;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="started", type="boolean")
     */
    private $started;

    /**
     * @var int
     *
     * @ORM\Column(name="progress", type="integer")
     */
    private $progress;
    
    /**
     * @var string[]
     *
     * @ORM\Column(name="arguments", type="json", nullable=true)
     */
    private $arguments;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255, nullable=true)
     */
    private $user;

    /**
     * @var string|null
     *
     * @ORM\Column(name="service", type="string", length=255, nullable=true)
     */
    private $service;

    /**
     * @var null|string
     *
     * @ORM\Column(name="command", type="string", length=2000, nullable=true)
     */
    private $command;
    
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

    /**
     * @param string[] $arguments
     */
    public function setArguments(array $arguments): Job
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
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

    public function setService(string $service): Job
    {
        $this->service = $service;

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setCommand(string $command): Job
    {
        $this->command = $command;

        return $this;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }
}
