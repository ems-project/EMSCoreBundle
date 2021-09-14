<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="release")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Release implements EntityInterface
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
     * @var \Datetime|null
     *
     * @ORM\Column(name="execution_date", type="datetime", nullable=true)
     */
    private $executionDate;

    /**
     * @var string
     * @ORM\Column(type="release_status_enum")
     */
    private $status;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var Environment
     * @ORM\ManyToOne(targetEntity="Environment")
     * @ORM\JoinColumn(name="environment_source_id", referencedColumnName="id")
     */
    private $environmentSource;

    /**
     * @var Environment
     * @ORM\ManyToOne(targetEntity="Environment")
     * @ORM\JoinColumn(name="environment_target_id", referencedColumnName="id")
     */
    private $environmentTarget;

    /**
     * @ORM\ManyToMany(targetEntity="Revision", cascade={"persist"})
     * @ORM\JoinTable(name="revision_release",
     *      joinColumns={@ORM\JoinColumn(name="release_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="revision_id", referencedColumnName="id")}
     *      )
     */
    private $revisions;

    public function __construct()
    {
        $this->revisions = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return \Datetime
     */
    public function getExecutionDate(): ?\Datetime
    {
        return $this->executionDate;
    }

    public function setExecutionDate(?\Datetime $executionDate): void
    {
        $this->executionDate = $executionDate;
    }

    public function setEnvironmentSource(Environment $environmentSource): Release
    {
        $this->environmentSource = $environmentSource;

        return $this;
    }

    public function getEnvironmentSource(): Environment
    {
        return $this->environmentSource;
    }

    public function setEnvironmentTarget(Environment $environmentTarget): Release
    {
        $this->environmentTarget = $environmentTarget;

        return $this;
    }

    public function getEnvironmentTarget(): Environment
    {
        return $this->environmentTarget;
    }

    /**
     * Add revision.
     */
    public function addRevision(Revision $revision): Release
    {
        $this->revisions[] = $revision;

        return $this;
    }

    /**
     * Remove revision.
     */
    public function removeRevision(Revision $revision): void
    {
        $this->revisions->removeElement($revision);
    }

    /**
     * Get revisions.
     *
     * @return array<Revision>
     */
    public function getRevisions(): array
    {
        return $this->revisions->toArray();
    }

    /**
     * @return array<string>
     */
    public function getRevisionsIds(): array
    {
        $ids = [];
        foreach ($this->getRevisions() as $revision) {
            $ids[] = \strval($revision->getId());
        }

        return $ids;
    }
}
