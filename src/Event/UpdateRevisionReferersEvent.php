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
    final public const string NAME = 'ems_core.revision.update_referers';

    /**
     * @param array<mixed> $toClean
     * @param array<mixed> $toCreate
     */
    public function __construct(private readonly string $type, private readonly string $id, private readonly string $targetField, private readonly array $toClean, private readonly array $toCreate)
    {
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
