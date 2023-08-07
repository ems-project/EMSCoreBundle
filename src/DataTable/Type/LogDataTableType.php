<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Log\LogEntityTableContext;
use EMS\CoreBundle\Core\Log\LogManager;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

class LogDataTableType extends AbstractEntityTableType
{
    public function __construct(LogManager $logManager)
    {
        parent::__construct($logManager);
    }

    public function getContext(array $options): LogEntityTableContext
    {
        return new LogEntityTableContext();
    }

    public function build(EntityTable $table): void
    {
        $table->setLabelAttribute('id');
        $table->addColumnDefinition(new DatetimeTableColumn('log.index.column.created', 'created'));
        $table->addColumn('log.index.column.channel', 'channel');
        $table->addColumn('log.index.column.level_name', 'levelName');
        $table->addColumn('log.index.column.message', 'message');
        $table->addColumnDefinition(new UserTableColumn('log.index.column.username', 'username'));
        $table->addColumnDefinition(new UserTableColumn('log.index.column.impersonator', 'impersonator'));
        $table->addItemGetAction(Routes::LOG_VIEW, 'view.actions.view', 'eye');
        $table->addItemPostAction(Routes::LOG_DELETE, 'view.actions.delete', 'trash', 'view.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'log.actions.delete_selected', 'log.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('created', 'desc');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
