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

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::EXPORT => t('ems_core.dashboard.export.label', [], 'emsco-core')->trans($translator),
            self::REVISION_TASK => t('ems_core.dashboard.revision_task.label', [], 'emsco-core')->trans($translator),
            self::TEMPLATE => t('ems_core.dashboard.template.label', [], 'emsco-core')->trans($translator)
        };
    }
}
