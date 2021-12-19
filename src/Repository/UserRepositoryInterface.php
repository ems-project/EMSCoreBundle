<?php

namespace EMS\CoreBundle\Repository;

use EMS\CoreBundle\Core\User\UserList;
use EMS\CoreBundle\Entity\User;

interface UserRepositoryInterface
{
    /**
     * @param string        $role
     * @param array<string> $circles
     *
     * @return array <User>
     */
    public function findForRoleAndCircles($role, $circles): array;

    public function getUsersEnabled(): UserList;
}
