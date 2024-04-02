<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Wysiwyg;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\WysiwygProfileService;

class WysiwygProfileDataTableType extends AbstractEntityTableType
{
    public function __construct(WysiwygProfileService $entityService)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('view.wysiwyg.index.column.profileName', 'name');
        $table->addItemGetAction('ems_wysiwyg_profile_edit', 'wysiwyg.actions.edit_button', 'edit', ['id' => 'id'])->setDynamic(true);
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'view.wysiwyg.actions.delete_selected', 'view.wysiwyg.actions.delete_selected_confirm');
        $table->setDefaultOrder('orderKey');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
