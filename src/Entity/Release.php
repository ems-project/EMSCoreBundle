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
     * @ORM\Column(type="releasestatusenum")
     */
    private $status;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @ORM\ManyToMany(targetEntity="Environment", cascade={"persist"})
     * @ORM\JoinTable(name="environment_release",
     *      joinColumns={@ORM\JoinColumn(name="release_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="environment_id", referencedColumnName="id")}
     *      )
     */
    private $environments;

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
        $this->environments = new \Doctrine\Common\Collections\ArrayCollection();
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

    public function setExecutionDate(\Datetime $executionDate): void
    {
        $this->executionDate = $executionDate;
    }

    /**
     * Add environment.
     */
    public function addEnvironment(Environment $environment): Release
    {
        $this->environments[] = $environment;

        return $this;
    }

    /**
     * Remove environment.
     */
    public function removeEnvironment(Environment $environment): void
    {
        $this->environments->removeElement($environment);
    }

    /**
     * Get environments.
     *
     * @return array<Environment>
     */
    public function getEnvironments(): array
    {
        return $this->environments->toArray();
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
