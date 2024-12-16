<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

enum ViewDefinition: string
{
    case DEFAULT_OVERVIEW = 'default_overview';

    public function getIcon(): string
    {
        return match ($this) {
            self::DEFAULT_OVERVIEW => 'fa fa-list',
        };
    }
}
