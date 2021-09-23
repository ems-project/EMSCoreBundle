<?php

namespace EMS\CoreBundle\DBAL;

class ReleaseStatusEnumType extends EnumType
{
    /** @var string */
    public const WIP_STATUS = 'delete';
    /** @var string */
    public const READY_STATUS = 'ready';
    /** @var string */
    public const APPLIED_STATUS = 'apply';
    /** @var string */
    public const CANCELED_STATUS = 'canceled';
    /** @var string */
    public const SCHEDULED_STATUS = 'scheduled';
    /** @var string */
    public const ROLLBACKED_STATUS = 'rollback';

    /**
     * @var string
     */
    protected $name = 'release_status_enum';
    /**
     * @var array<string>
     */
    protected $values = ['wip', 'ready', 'apply', 'scheduled', 'canceled', 'rollback'];

    /**
     * @return array<string>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
