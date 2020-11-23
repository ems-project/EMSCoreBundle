<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="form_submission_file")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class FormSubmissionFile
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
     * @var FormSubmission
     *
     * @ORM\ManyToOne(targetEntity="EMS\CoreBundle\Entity\FormSubmission", inversedBy="files")
     * @ORM\JoinColumn(name="form_submission_id", referencedColumnName="id")
     */
    private $formSubmission;

    /**
     * @var string|resource
     *
     * @ORM\Column(name="file", type="blob")
     */
    private $file;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="string")
     */
    private $filename;

    /**
     * @var string
     *
     * @ORM\Column(name="form_field", type="string")
     */
    private $formField;

    /**
     * @var string
     *
     * @ORM\Column(name="mime_type", type="string", length=1024)
     */
    private $mimeType;

    /**
     * @ORM\Column(name="size", type="bigint")
     */
    private $size;

    /**
     * @param array<string, string> $file
     */
    public function __construct(FormSubmission $formSubmission, array $file)
    {
        $now = new \DateTime();

        $this->id = Uuid::uuid4();
        $this->created = $now;
        $this->modified = $now;

        $this->formSubmission = $formSubmission;
        $this->file = base64_decode($file['base64']);
        $this->filename = $file['filename'];
        $this->formField = $file['form_field'];
        $this->mimeType = $file['mimeType'];
        $this->size = $file['size'];
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
     * @return resource|null
     */
    public function getFile()
    {
        return \is_resource($this->file) ? $this->file : null;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }
}
