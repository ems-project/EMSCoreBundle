<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

class JobScheduleDataTableType extends AbstractEntityTableType
{
    public function __construct(ScheduleManager $entityService)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('schedule.index.column.name', 'name');
        $table->addColumn('schedule.index.column.cron', 'cron');
        $table->addColumn('schedule.index.column.command', 'command');
        $table->addColumn('schedule.index.column.tag', 'tag');
        $table->addColumnDefinition(new DatetimeTableColumn('schedule.index.column.previous-run', 'previousRun'));
        $table->addColumnDefinition(new DatetimeTableColumn('schedule.index.column.next-run', 'nextRun'));
        $table->addItemGetAction(Routes::SCHEDULE_EDIT, 'view.actions.edit', 'pencil');
        $table->addItemPostAction(Routes::SCHEDULE_DUPLICATE, 'view.actions.duplicate', 'pencil', 'view.actions.duplicate_confirm');
        $table->addItemPostAction(Routes::SCHEDULE_DELETE, 'view.actions.delete', 'trash', 'view.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'schedule.actions.delete_selected', 'schedule.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('orderKey');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
