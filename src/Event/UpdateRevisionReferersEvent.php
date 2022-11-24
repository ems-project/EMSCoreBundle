<?php

namespace EMS\CoreBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Contains information to update 2 side links between objects.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class UpdateRevisionReferersEvent extends Event
{
    public const NAME = 'ems_core.revision.update_referers';

    private string $targetField;
    private string $id;
    /** @var array<mixed> */
    private array $toClean;
    /** @var array<mixed> */
    private array $toCreate;
    private string $type;

    /**
     * @param array<mixed> $toCleanOuuids
     * @param array<mixed> $toCreateOuuids
     */
    public function __construct(string $type, string $id, string $targetField, array $toCleanOuuids, array $toCreateOuuids)
    {
        $this->type = $type;
        $this->id = $id;
        $this->targetField = $targetField;
        $this->toClean = $toCleanOuuids;
        $this->toCreate = $toCreateOuuids;
    }

    /**
     * Return the name of the computed field where the back link is store.
     */
    public function getTargetField(): string
    {
        return $this->targetField;
    }

    /**
     * List of UUIDs where the back link should be removed.
     *
     * @return array<mixed>
     */
    public function getToCleanOuuids(): array
    {
        return \array_diff($this->toClean, $this->toCreate);
    }

    /**
     * List of UUIDs where the back link should be added.
     *
     * @return array<mixed>
     */
    public function getToCreateOuuids(): array
    {
        return \array_diff($this->toCreate, $this->toClean);
    }

    /**
     * Type of the object triggering the event.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Id of the object triggering the event.
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getRefererOuuid(): string
    {
        return $this->getType().':'.$this->getId();
    }
}
