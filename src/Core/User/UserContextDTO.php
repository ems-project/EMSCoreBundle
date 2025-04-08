<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

readonly class UserContextDTO
{
    public function __construct(public bool $light, public bool $inGroup, public ?string $groupId)
    {
    }
}
