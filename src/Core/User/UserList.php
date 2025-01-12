<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

use EMS\CoreBundle\Entity\User;

class UserList
{
    /**
     * @param array<User> $users
     */
    public function __construct(private readonly array $users)
    {
    }

    /**
     * @return array <User>
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * @param array<string> $roles
     */
    public function getForRoles(array $roles): UserList
    {
        $usersList = [];
        /** User $user */
        foreach ($this->getUsers() as $user) {
            if (!empty(\array_intersect($roles, $user->getRoles()))) {
                $usersList[] = $user;
            }
        }

        return new UserList($usersList);
    }

    /**
     * @param array<string> $circles
     */
    public function getForCircles(array $circles): UserList
    {
        $usersList = [];
        /** User $user */
        foreach ($this->getUsers() as $user) {
            if (!empty(\array_intersect($circles, $user->getCircles()))) {
                $usersList[] = $user;
            }
        }

        return new UserList($usersList);
    }
}
