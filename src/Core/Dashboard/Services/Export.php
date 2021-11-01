<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

class Export implements DashboardInterface
{
    public static function getLabel(): string
    {
        return 'Export';
    }

    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-export';
    }
}
