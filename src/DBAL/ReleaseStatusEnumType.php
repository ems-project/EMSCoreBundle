<?php

namespace EMS\CoreBundle\DBAL;

class ReleaseStatusEnumType extends EnumType
{
    /**
     * @var string
     */
    protected $name = 'releasestatusenum';
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
