<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard;

enum DashboardDefinition: string
{
    case LANDING_PAGE = 'landing_page';
    case QUICK_SEARCH = 'quick_search';
    case BROWSER_IMAGE = 'browser_image';
    case BROWSER_OBJECT = 'browser_object';
    case BROWSER_FILE = 'browser_file';

    public function getIcon(): string
    {
        return match ($this) {
            self::LANDING_PAGE => 'dot-circle-o',
            self::QUICK_SEARCH => 'search',
            self::BROWSER_IMAGE => 'image',
            self::BROWSER_OBJECT => 'book',
            self::BROWSER_FILE => 'file-image-o',
        };
    }
}
