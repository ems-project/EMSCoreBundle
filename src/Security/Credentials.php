<?php

namespace EMS\CoreBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class Credentials
{
    private function __construct()
    {
    }

    public static function usernamePasswordToken(Request $request, string $providerKey): UsernamePasswordToken
    {
        $loginInfo = \json_decode((string) $request->getContent(), true);

        $username = $loginInfo['username'] ?? '';
        $password = $loginInfo['password'] ?? '';

        if ('' === $username || '' === $password) {
            throw new \RuntimeException('Username and Password should be provided and different from ""');
        }

        return new UsernamePasswordToken($username, $password, $providerKey);
    }
}
