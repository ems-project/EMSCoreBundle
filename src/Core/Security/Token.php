<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Security;

final class Token
{
    public static function generate(): string
    {
        return \rtrim(\strtr(\base64_encode(\random_bytes(32)), '+/', '-_'), '=');
    }
}
