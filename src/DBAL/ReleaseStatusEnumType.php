<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DBAL;

class ReleaseStatusEnumType extends EnumType
{
    public const WIP_STATUS = 'wip';
    public const READY_STATUS = 'ready';
    public const APPLIED_STATUS = 'applied';
    public const CANCELED_STATUS = 'canceled';
    public const SCHEDULED_STATUS = 'scheduled';
    public const ROLLBACKED_STATUS = 'rollbacked';

    protected string $name = 'release_status_enum';
    /** @var array<string> */
    protected array $values = ['wip', 'ready', 'applied', 'scheduled', 'canceled', 'rollbacked'];

    /**
     * @return array<string>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
