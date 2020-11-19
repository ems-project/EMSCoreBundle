<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\User\UserList;
use EMS\CoreBundle\Repository\UserRepository;
use Twig\Extension\RuntimeExtensionInterface;


class UserRuntime implements RuntimeExtensionInterface
{
    /** @var UserRepository */
    private $userRepository;
    
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    
    /**
     * @return UserList
     */
    public function getUsersEnabled(): UserList
    {
        return $this->userRepository->getUsersEnabled();
    }
}
