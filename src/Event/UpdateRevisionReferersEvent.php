<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\Revision;
use Symfony\Contracts\EventDispatcher\Event;

class UpdateRevisionReferersEvent extends Event
{
    private Revision $revision;
    private string $targetField;
    /** @var string[] */
    private array $removeOuuids;
    /** @var string[] */
    private array $addOuuids;

    /**
     * @param string[] $removeOuuids
     * @param string[] $addOuuids
     */
    public function __construct(Revision $revision, string $targetField, array $removeOuuids, array $addOuuids)
    {
        $this->revision = $revision;
        $this->targetField = $targetField;
        $this->removeOuuids = $removeOuuids;
        $this->addOuuids = $addOuuids;
    }

    public function getTargetField(): string
    {
        return $this->targetField;
    }

    /**
     * @return EMSLink[]
     */
    public function getRemoveEmsLinks(): array
    {
        $removeOuuids = \array_diff($this->removeOuuids, $this->addOuuids);

        return \array_map(fn (string $ouuid) => EMSLink::fromText($ouuid), $removeOuuids);
    }

    /**
     * @return EMSLink[]
     */
    public function getAddEmsLinks(): array
    {
        return \array_map(fn (string $ouuid) => EMSLink::fromText($ouuid), $this->addOuuids);
    }

    public function getRefererOuuid(): string
    {
        return $this->revision->getEmsId();
    }
}
