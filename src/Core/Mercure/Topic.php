<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mercure;

enum Topic: string
{
    case NOTIFICATIONS = 'notifications';
    case USER = 'user/{id}';
}
