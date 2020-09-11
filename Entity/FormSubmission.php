<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionRequest;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="form_submission")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class FormSubmission
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
     * @ORM\Column(name="instance", type="string", length=255)
     */
    private $instance;

    /**
     * @var string
     *
     * @ORM\Column(name="locale", type="string", length=2)
     */
    private $locale;

    /**
     * @var null|array<string, mixed>
     *
     * @ORM\Column(name="data", type="json_array", nullable=true)
     */
    private $data;

    /**
     * @var Collection<int, FormSubmissionFile>
     *
     * @ORM\OneToMany(targetEntity="FormSubmissionFile", mappedBy="formSubmission", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $files;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    private $label;

    /**
     * @var string
     *
     * @ORM\Column(name="deadline_date", type="string", length=255)
     */
    private $deadlineDate;

    /**
     * @var int
     *
     * @ORM\Column(name="process_try_counter", type="integer", nullable=false, options={"default": 0})
     */
    private $processTryCounter;

    /**
     * @var string
     *
     * @ORM\Column(name="process_id", type="string", length=255, nullable=true)
     */
    private $processId;

    /**
     * @var string
     *
     * @ORM\Column(name="process_by", type="string", length=255, nullable=true)
     */
    private $processBy;

    public function __construct(FormSubmissionRequest $submitRequest)
    {
        $now = new \DateTime();

        $this->id = Uuid::uuid4();
        $this->created = $now;
        $this->modified = $now;
        $this->processTryCounter = 0;

        $this->name = $submitRequest->getFormName();
        $this->instance = $submitRequest->getInstance();
        $this->locale = $submitRequest->getLocale();
        $this->data = $submitRequest->getData();

        $this->files = new ArrayCollection();

        $this->label = $submitRequest->getLabel();
        $this->deadlineDate = $submitRequest->getDeadlineDate();

        foreach ($submitRequest->getFiles() as $file) {
            $this->files->add(new FormSubmissionFile($this, $file));
        }
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

    /**
     * @return null|array<string, mixed>
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @return Collection<int, FormSubmissionFile>|FormSubmissionFile[]
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function getInstance(): string
    {
        return $this->instance;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDeadlineDate(): string
    {
        return $this->deadlineDate;
    }

    public function getCreated(): \Datetime
    {
        return $this->created;
    }

    public function process(User $user): void
    {
        $this->data = null;
        $this->processTryCounter = 1;
        $this->processBy = $user->getUsername();
        $this->files->clear();
    }

    public function getProcessTryCounter(): int
    {
        return $this->processTryCounter;
    }

    public function setProcessTryCounter(int $processTryCounter): FormSubmission
    {
        $this->processTryCounter = $processTryCounter;
        return $this;
    }

    public function getProcessId(): ?string
    {
        return $this->processId;
    }

    public function setProcessId(string $processId): FormSubmission
    {
        $this->processId = $processId;
        return $this;
    }
}
