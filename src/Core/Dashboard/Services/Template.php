<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

class Template implements DashboardInterface
{
    public static function getLabel(): string
    {
        return 'Template';
    }

    public static function getIcon(): string
    {
        return 'fa fa-html-5';
    }
}
