<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

enum DashboardType: string implements TranslatableInterface
{
    case EXPORT = 'ems_core.dashboard.export';
    case REVISION_TASK = 'ems_core.dashboard.revision_task';
    case TEMPLATE = 'ems_core.dashboard.template';
    case LEGACY_SEARCH = 'ems_core.dashboard.advanced_search';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::EXPORT => t('action.export', [], 'emsco-core')->trans($translator),
            self::REVISION_TASK => t('key.revision_tasks', [], 'emsco-core')->trans($translator),
            self::TEMPLATE => t('key.template', [], 'emsco-core')->trans($translator),
            self::LEGACY_SEARCH => t('key.advanced_search', [], 'emsco-core')->trans($translator)
        };
    }
}
