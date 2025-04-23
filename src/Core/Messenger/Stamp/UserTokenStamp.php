<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserTokenStamp implements StampInterface
{
    public function __construct(
        public TokenInterface $token
    ) {
    }

    public function getToken(): TokenInterface
    {
        return $this->token;
    }
}
