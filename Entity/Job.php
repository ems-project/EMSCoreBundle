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
     * @var array
     *
     * @ORM\Column(name="arguments", type="json_array", nullable=true)
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
     * @ORM\Column(name="command", type="string", length=255, nullable=true)
     */
    private $command;
    
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
     * Get id
     *
     * @return integer
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
     * @return Job
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
     * Get started
     *
     * @return bool
     */
    public function getStarted()
    {
        return $this->started;
    }

    /**
     * Set modified
     *
     * @param \DateTime $modified
     *
     * @return Job
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
     * Set user
     *
     * @param string $user
     *
     * @return Job
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set status
     *
     * @param string $status
     *
     * @return Job
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Set done
     *
     * @param boolean $done
     *
     * @return Job
     */
    public function setDone($done)
    {
        $this->done = $done;
        
        return $this;
    }
    
    /**
     * Set started
     *
     * @param boolean $started
     *
     * @return Job
     */
    public function setStarted($started)
    {
        $this->started = $started;
        
        return $this;
    }

    /**
     * Get done
     *
     * @return boolean
     */
    public function getDone()
    {
        return $this->done;
    }

    /**
     * Set progress
     *
     * @param integer $progress
     *
     * @return Job
     */
    public function setProgress($progress)
    {
        $this->progress = $progress;

        return $this;
    }

    /**
     * Get progress
     *
     * @return integer
     */
    public function getProgress()
    {
        return $this->progress;
    }

    /**
     * Set arguments
     *
     * @param array $arguments
     *
     * @return Job
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Get arguments
     *
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Set output
     *
     * @param string $output
     *
     * @return Job
     */
    public function setOutput($output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Get output
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set service
     *
     * @param string $service
     *
     * @return Job
     */
    public function setService($service)
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Get service
     *
     * @return string|null
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Set command
     *
     * @param string $command
     *
     * @return Job
     */
    public function setCommand($command)
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Get command
     *
     * @return string|null
     */
    public function getCommand()
    {
        return $this->command;
    }
}
