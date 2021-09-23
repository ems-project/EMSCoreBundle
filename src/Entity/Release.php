<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\DBAL\ReleaseStatusEnumType;

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
     * @ORM\OneToMany(targetEntity="ReleaseRevision", mappedBy="release", cascade={"persist", "remove"})
     */
    private $revisions;

    public function __construct()
    {
        $this->revisions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->status = ReleaseStatusEnumType::WIP_STATUS;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): Release
    {
        $this->name = $name;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): Release
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return \Datetime
     */
    public function getExecutionDate(): ?\Datetime
    {
        return $this->executionDate;
    }

    public function setExecutionDate(?\Datetime $executionDate): Release
    {
        $this->executionDate = $executionDate;

        return $this;
    }

    public function setEnvironmentSource(Environment $environmentSource): Release
    {
        $this->environmentSource = $environmentSource;

        return $this;
    }

    public function getEnvironmentSource(): ?Environment
    {
        return $this->environmentSource;
    }

    public function setEnvironmentTarget(Environment $environmentTarget): Release
    {
        $this->environmentTarget = $environmentTarget;

        return $this;
    }

    public function getEnvironmentTarget(): ?Environment
    {
        return $this->environmentTarget;
    }

    /**
     * Add revision.
     */
    public function addRevision(ReleaseRevision $revision): Release
    {
        $this->revisions[] = $revision;

        return $this;
    }

    /**
     * Remove revision.
     */
    public function removeRevision(ReleaseRevision $revision): void
    {
        $this->revisions->removeElement($revision);
    }

    /**
     * Get revisions.
     *
     * @return array<ReleaseRevision>
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
            $ids[] = \strval($revision->getRevisionOuuid());
        }

        return $ids;
    }
}
