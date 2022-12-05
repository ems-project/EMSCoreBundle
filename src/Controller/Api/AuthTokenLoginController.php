<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

class AuthTokenLoginController
{
    public function login(): never
    {
        throw new \RuntimeException('AuthTokenLogin authenticator should answer');
    }
}
