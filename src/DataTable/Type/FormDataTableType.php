<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

class FormDataTableType extends AbstractEntityTableType
{
    public function __construct(FormManager $entityService)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('form.index.column.name', 'name');
        $table->addColumn('form.index.column.label', 'label');
        $table->addItemGetAction(Routes::FORM_ADMIN_EDIT, 'form.actions.edit', 'pencil');
        $table->addItemGetAction(Routes::FORM_ADMIN_REORDER, 'form.actions.reorder', 'reorder');
        $table->addItemPostAction(Routes::FORM_ADMIN_DELETE, 'form.actions.delete', 'trash', 'form.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'form.actions.delete_selected', 'form.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('orderKey');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
