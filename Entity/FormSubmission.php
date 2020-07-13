<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Service\FormSubmission\SubmitRequest;

/**
 * @ORM\Table(name="form_submission")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class FormSubmission
{
    /**
     * @var null|int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
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
     * @ORM\Column(name="locale", type="string", length=2)
     */
    private $locale;

    /**
     * @var array<string, mixed>
     *
     * @ORM\Column(name="data", type="json")
     */
    private $data;

    /**
     * @var Collection<int, FormSubmissionFile>
     *
     * @ORM\OneToMany(targetEntity="FormSubmissionFile", mappedBy="formSubbmission", cascade={"persist", "remove"})
     */
    protected $files;

    public function __construct(SubmitRequest $submitRequest)
    {
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
        $this->name = $submitRequest->getFormName();
        $this->locale = $submitRequest->getLocale();
        $this->data = $submitRequest->getData();

        $this->files = new ArrayCollection();

        foreach ($submitRequest->getFiles() as $file) {
            $this->files->add(new FormSubmissionFile($this, $file));
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified(): void
    {
        $this->modified = new \DateTime();
    }
}
