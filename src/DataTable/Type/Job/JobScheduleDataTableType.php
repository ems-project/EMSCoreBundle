<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Job;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class JobScheduleDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(ScheduleManager $scheduleManager)
    {
        parent::__construct($scheduleManager);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('orderKey');

        $table->addColumn(t('key.loop_count', [], 'emsco-core'), 'orderKey');
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $table->addColumn(t('field.cron', [], 'emsco-core'), 'cron');
        $table->addColumn(t('field.command', [], 'emsco-core'), 'command');
        $table->addColumn(t('field.tag', [], 'emsco-core'), 'tag');

        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_run_previous', [], 'emsco-core'),
            attribute: 'previousRun'
        ));
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_run_next', [], 'emsco-core'),
            attribute: 'nextRun'
        ));

        $this->addItemEdit($table, Routes::SCHEDULE_EDIT);
        $table->addItemPostAction(
            route: Routes::SCHEDULE_DUPLICATE,
            labelKey: t('action.duplicate', [], 'emsco-core'),
            icon: 'files-o',
            messageKey: t('action.confirmation', [], 'emsco-core')
        );

        $this
            ->addItemDelete($table, 'job_schedule', Routes::SCHEDULE_DELETE)
            ->addTableToolbarActionAdd($table, Routes::SCHEDULE_ADD)
            ->addTableActionDelete($table, 'job_schedule');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
