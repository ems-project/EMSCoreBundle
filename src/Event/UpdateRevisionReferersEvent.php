<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use EMS\CommonBundle\Common\EMSLink;
use Symfony\Contracts\EventDispatcher\Event;

class UpdateRevisionReferersEvent extends Event
{
    private string $type;
    private string $id;
    private string $targetField;
    /** @var string[] */
    private array $removeOuuids;
    /** @var string[] */
    private array $addOuuids;

    /**
     * @param string[] $removeOuuids
     * @param string[] $addOuuids
     */
    public function __construct(string $type, string $id, string $targetField, array $removeOuuids, array $addOuuids)
    {
        $this->type = $type;
        $this->id = $id;
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
        $addOuuids = \array_diff($this->addOuuids, $this->removeOuuids);

        return \array_map(fn (string $ouuid) => EMSLink::fromText($ouuid), $addOuuids);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRefererOuuid(): string
    {
        return $this->getType().':'.$this->getId();
    }
}
