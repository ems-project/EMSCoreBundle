<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\DBAL\ReleaseStatusEnumType;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @ORM\Table(name="release")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Release implements EntityInterface
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(name="execution_date", type="datetime", nullable=true)
     */
    private ?\Datetime $executionDate;

    /**
     * @ORM\Column(type="release_status_enum")
     */
    private string $status;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     */
    private string $name;

    /**
     * @ORM\ManyToOne(targetEntity="Environment")
     * @ORM\JoinColumn(name="environment_source_id", referencedColumnName="id")
     */
    private Environment $environmentSource;

    /**
     * @ORM\ManyToOne(targetEntity="Environment")
     * @ORM\JoinColumn(name="environment_target_id", referencedColumnName="id")
     */
    private Environment $environmentTarget;

    /**
     * @ORM\OneToMany(targetEntity="ReleaseRevision", mappedBy="release", cascade={"persist", "remove"})
     */
    private $revisions;

    public function __construct()
    {
        $this->revisions = new ArrayCollection();
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

    public function addRevision(ReleaseRevision $revision): Release
    {
        $this->revisions[] = $revision;

        return $this;
    }

    public function removeRevision(ReleaseRevision $revision): void
    {
        $this->revisions->removeElement($revision);
    }

    /**
     * @return ReleaseRevision[]
     */
    public function getRevisions(): array
    {
        return $this->revisions->toArray();
    }

    /**
     * @return string[]
     */
    public function getRevisionsIds(): array
    {
        $ids = [];
        foreach ($this->getRevisions() as $revision) {
            $ids[] = $revision->getRevisionOuuid();
        }

        return $ids;
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->getEnvironmentTarget() === $this->getEnvironmentSource()) {
            $context->buildViolation('entity.release.violation.same_source_and_target')
                ->setTranslationDomain(EMSCoreBundle::TRANS_DOMAIN_VALIDATORS)
                ->atPath('environmentTarget')
                ->addViolation();
        }
    }
}
