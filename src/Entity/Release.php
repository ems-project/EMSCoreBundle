<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Core\Revision\Release\ReleaseRevisionType;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class Release implements EntityInterface
{
    use IdentifierIntegerTrait;

    final public const string WIP_STATUS = 'wip';
    final public const string READY_STATUS = 'ready';
    final public const string APPLIED_STATUS = 'applied';
    final public const string CANCELED_STATUS = 'canceled';
    final public const string SCHEDULED_STATUS = 'scheduled';

    private ?\DateTime $executionDate = null;
    private string $status = Release::WIP_STATUS;
    private string $name;
    private Environment $environmentSource;
    private Environment $environmentTarget;
    /** @var Collection<int, ReleaseRevision> */
    private Collection $revisions;

    public function __construct()
    {
        $this->revisions = new ArrayCollection();
    }

    #[\Override]
    public function getName(): string
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

    public function getExecutionDate(): ?\DateTime
    {
        return $this->executionDate;
    }

    public function setExecutionDate(?\DateTime $executionDate): Release
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

    public function addRevision(Revision $revision, ReleaseRevisionType $type): Release
    {
        $releaseRevision = new ReleaseRevision(
            release: $this,
            revision: $revision,
            type: $type
        );

        $this->revisions[] = $releaseRevision;

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
    public function getRevisionsOuuids(): array
    {
        $ids = [];
        foreach ($this->getRevisions() as $revision) {
            $ids[] = $revision->getRevisionOuuid();
        }

        return $ids;
    }

    #[Assert\Callback]
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
