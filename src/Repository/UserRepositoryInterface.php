<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use EMS\CoreBundle\Core\User\UserList;
use EMS\CoreBundle\Entity\User;

interface UserRepositoryInterface
{
    /**
     * @param array<string> $circles
     *
     * @return User[]
     */
    public function findForRoleAndCircles(string $role, array $circles): array;

    public function getUsersEnabled(): UserList;
}
