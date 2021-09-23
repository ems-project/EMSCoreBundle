<?php

namespace EMS\CoreBundle\DBAL;

class ReleaseStatusEnumType extends EnumType
{
    /** @var string */
    public const WIP_STATUS = 'wip';
    /** @var string */
    public const READY_STATUS = 'ready';
    /** @var string */
    public const APPLIED_STATUS = 'applied';
    /** @var string */
    public const CANCELED_STATUS = 'canceled';
    /** @var string */
    public const SCHEDULED_STATUS = 'scheduled';
    /** @var string */
    public const ROLLBACKED_STATUS = 'rollbacked';

    /**
     * @var string
     */
    protected $name = 'release_status_enum';
    /**
     * @var array<string>
     */
    protected $values = ['wip', 'ready', 'applied', 'scheduled', 'canceled', 'rollbacked'];

    /**
     * @return array<string>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
