<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\Helpers\Standard\DateTime;

/**
 * @ORM\Table(name="notification")
 *
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\NotificationRepository")
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Notification implements \Stringable
{
    use CreatedModifiedTrait;
    final public const PENDING = 'pending';
    final public const IN_TRANSIT = 'in-transit';

    /**
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\ManyToOne(targetEntity="Template")
     *
     * @ORM\JoinColumn(name="template_id", referencedColumnName="id")
     */
    private ?Template $template = null;

    /**
     * @ORM\Column(name="username", type="string", length=100)
     */
    private string $username;

    /**
     * @ORM\Column(name="status", type="string", length=20)
     */
    private string $status;

    /**
     * @ORM\Column(name="sent_timestamp", type="datetime")
     */
    private ?\DateTime $sentTimestamp = null;

    /**
     * @ORM\Column(name="response_text", type="text", nullable=true)
     */
    private ?string $responseText = null;

    /**
     * @ORM\Column(name="response_timestamp", type="datetime", nullable=true)
     */
    private ?\DateTime $responseTimestamp = null;

    /**
     * @ORM\Column(name="response_by", type="string", length=100, nullable=true)
     */
    private string $responseBy;

    /**
     * @ORM\ManyToOne(targetEntity="Revision", inversedBy="notifications")
     *
     * @ORM\JoinColumn(name="revision_id", referencedColumnName="id")
     */
    private ?Revision $revision = null;

    /**
     * @ORM\ManyToOne(targetEntity="Environment")
     *
     * @ORM\JoinColumn(name="environment_id", referencedColumnName="id")
     */
    private ?Environment $environment = null;

    /**
     * @ORM\Column(name="emailed", type="datetime", nullable=true)
     */
    private \DateTime $emailed;

    /**
     * @ORM\Column(name="response_emailed", type="datetime", nullable=true)
     */
    private \DateTime $responseEmailed;

    private int $counter = 0;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function __toString(): string
    {
        return $this->getTemplate()->getName().'#'.$this->id;
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
     * Set template.
     */
    public function setTemplate(?Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate(): Template
    {
        if (null === $this->template) {
            throw new \RuntimeException('Missing template');
        }

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

    public function setSentTimestamp(\DateTime $sentTimestamp): Notification
    {
        $this->sentTimestamp = $sentTimestamp;

        return $this;
    }

    /**
     * Get counter.
     */
    public function getCounter(): int
    {
        return $this->counter;
    }

    public function setCounter(int $counter): self
    {
        $this->counter = $counter;

        return $this;
    }

    public function getSentTimestamp(): ?\DateTime
    {
        return $this->sentTimestamp;
    }

    public function setResponseText(?string $responseText): self
    {
        $this->responseText = $responseText;

        return $this;
    }

    public function getResponseText(): ?string
    {
        return $this->responseText;
    }

    public function setResponseTimestamp(\DateTime $responseTimestamp): Notification
    {
        $this->responseTimestamp = $responseTimestamp;

        return $this;
    }

    public function getResponseTimestamp(): ?\DateTime
    {
        return $this->responseTimestamp;
    }

    public function setRevision(?Revision $revision): self
    {
        $this->revision = $revision;

        return $this;
    }

    public function getRevision(): Revision
    {
        if (null === $this->revision) {
            throw new \RuntimeException('Missing revision');
        }

        return $this->revision;
    }

    public function setEnvironment(?Environment $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    public function getEnvironment(): Environment
    {
        if (null === $this->environment) {
            throw new \RuntimeException('Missing revision');
        }

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
