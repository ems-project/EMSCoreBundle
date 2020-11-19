<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Notification.
 *
 * @ORM\Table(name="notification")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\NotificationRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Notification
{
    const PENDING = 'pending';

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
     * @ORM\ManyToOne(targetEntity="Template")
     * @ORM\JoinColumn(name="template_id", referencedColumnName="id")
     */
    private $template;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=100)
     */
    private $username;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=20)
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="sent_timestamp", type="datetime")
     */
    private $sentTimestamp;

    /**
     * @var string
     *
     * @ORM\Column(name="response_text", type="text", nullable=true)
     */
    private $responseText;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="response_timestamp", type="datetime", nullable=true)
     */
    private $responseTimestamp;

    /**
     * @var string
     *
     * @ORM\Column(name="response_by", type="string", length=100, nullable=true)
     */
    private $responseBy;

    /**
     * @ORM\ManyToOne(targetEntity="Revision", inversedBy="notifications")
     * @ORM\JoinColumn(name="revision_id", referencedColumnName="id")
     */
    private $revision;

    /**
     * @ORM\ManyToOne(targetEntity="Environment")
     * @ORM\JoinColumn(name="environment_id", referencedColumnName="id")
     */
    private $environment;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="emailed", type="datetime", nullable=true)
     */
    private $emailed;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="response_emailed", type="datetime", nullable=true)
     */
    private $responseEmailed;

    private $counter;

    public function __toString()
    {
        return $this->getTemplate()->getName().'#'.$this->id;
    }

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
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return Notification
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified.
     *
     * @param \DateTime $modified
     *
     * @return Notification
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified.
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set template.
     *
     * @return Notification
     */
    public function setTemplate(\EMS\CoreBundle\Entity\Template $template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Get template.
     *
     * @return \EMS\CoreBundle\Entity\Template
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set username.
     *
     * @param string $username
     *
     * @return Notification
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set status.
     *
     * @param string $status
     *
     * @return Notification
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
     * Set sentTimestamp.
     *
     * @param \DateTime $sentTimestamp
     *
     * @return Notification
     */
    public function setSentTimestamp($sentTimestamp)
    {
        $this->sentTimestamp = $sentTimestamp;

        return $this;
    }

    /**
     * Get counter.
     *
     * @return int
     */
    public function getCounter()
    {
        return $this->counter;
    }

    /**
     * Set counter.
     *
     * @param int $counter
     *
     * @return Notification
     */
    public function setCounter($counter)
    {
        $this->counter = $counter;

        return $this;
    }

    /**
     * Get sentTimestamp.
     *
     * @return \DateTime
     */
    public function getSentTimestamp()
    {
        return $this->sentTimestamp;
    }

    /**
     * Set responseText.
     *
     * @param string $responseText
     *
     * @return Notification
     */
    public function setResponseText($responseText)
    {
        $this->responseText = $responseText;

        return $this;
    }

    /**
     * Get responseText.
     *
     * @return string
     */
    public function getResponseText()
    {
        return $this->responseText;
    }

    /**
     * Set responseTimestamp.
     *
     * @param \DateTime $responseTimestamp
     *
     * @return Notification
     */
    public function setResponseTimestamp($responseTimestamp)
    {
        $this->responseTimestamp = $responseTimestamp;

        return $this;
    }

    /**
     * Get responseTimestamp.
     *
     * @return \DateTime
     */
    public function getResponseTimestamp()
    {
        return $this->responseTimestamp;
    }

    public function setRevision(Revision $revision): Notification
    {
        $this->revision = $revision;

        return $this;
    }

    /**
     * Get revision.
     *
     * @return \EMS\CoreBundle\Entity\Revision
     */
    public function getRevision()
    {
        return $this->revision;
    }

    public function setEnvironment(Environment $environment): Notification
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Get environment.
     *
     * @return \EMS\CoreBundle\Entity\Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Set responseBy.
     *
     * @param string $responseBy
     *
     * @return Notification
     */
    public function setResponseBy($responseBy)
    {
        $this->responseBy = $responseBy;

        return $this;
    }

    /**
     * Get responseBy.
     *
     * @return string
     */
    public function getResponseBy()
    {
        return $this->responseBy;
    }

    /**
     * Set emailed.
     *
     * @param \DateTime $emailed
     *
     * @return Notification
     */
    public function setEmailed($emailed)
    {
        $this->emailed = $emailed;

        return $this;
    }

    /**
     * Get emailed.
     *
     * @return \DateTime
     */
    public function getEmailed()
    {
        return $this->emailed;
    }

    /**
     * Set responseEmailed.
     *
     * @param \DateTime $responseEmailed
     *
     * @return Notification
     */
    public function setResponseEmailed($responseEmailed)
    {
        $this->responseEmailed = $responseEmailed;

        return $this;
    }

    /**
     * Get responseEmailed.
     *
     * @return \DateTime
     */
    public function getResponseEmailed()
    {
        return $this->responseEmailed;
    }
}
