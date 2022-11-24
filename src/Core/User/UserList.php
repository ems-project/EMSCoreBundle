<?php

namespace EMS\CoreBundle\Core\User;

use EMS\CoreBundle\Entity\User;

class UserList
{
    /** @var User[] */
    private array $users;

    /**
     * @param array<User> $users
     */
    public function __construct(array $users)
    {
        $this->users = $users;
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
