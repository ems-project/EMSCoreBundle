<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

class DashboardDataTableType extends AbstractEntityTableType
{
    public function __construct(DashboardManager $entityService)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('dashboard.index.column.name', 'name');
        $table->addColumn('dashboard.index.column.label', 'label')->setItemIconCallback(fn (Dashboard $dashboard) => $dashboard->getIcon());
        $table->addColumnDefinition(new TemplateBlockTableColumn('dashboard.index.column.type', 'type', '@EMSCore/dashboard/columns.html.twig'));
        $table->addColumnDefinition(new TemplateBlockTableColumn('dashboard.index.column.definition', 'definition', '@EMSCore/dashboard/columns.html.twig'));
        $table->addItemGetAction(Routes::DASHBOARD_ADMIN_EDIT, 'dashboard.actions.edit', 'pencil');

        $defineAction = $table->addItemActionCollection('dashboard.actions.define.title', 'gear');
        $defineAction->addItemPostAction(Routes::DASHBOARD_ADMIN_DEFINE, 'dashboard.actions.define.landing_page', 'dot-circle-o', null, ['definition' => Dashboard::DEFINITION_LANDING_PAGE]);
        $defineAction->addItemPostAction(Routes::DASHBOARD_ADMIN_DEFINE, 'dashboard.actions.define.quick_search', 'search', null, ['definition' => Dashboard::DEFINITION_QUICK_SEARCH]);
        $defineAction->addItemPostAction(Routes::DASHBOARD_ADMIN_DEFINE, 'dashboard.actions.define.browser_image', 'image', null, ['definition' => Dashboard::DEFINITION_BROWSER_IMAGE]);
        $defineAction->addItemPostAction(Routes::DASHBOARD_ADMIN_DEFINE, 'dashboard.actions.define.browser_object', 'book', null, ['definition' => Dashboard::DEFINITION_BROWSER_OBJECT]);
        $defineAction->addItemPostAction(Routes::DASHBOARD_ADMIN_DEFINE, 'dashboard.actions.define.browser_file', 'file-image-o', null, ['definition' => Dashboard::DEFINITION_BROWSER_FILE]);
        $defineAction->addItemPostAction(Routes::DASHBOARD_ADMIN_UNDEFINE, 'dashboard.actions.undefine', 'eraser', null);

        $table->addItemPostAction(Routes::DASHBOARD_ADMIN_DELETE, 'dashboard.actions.delete', 'trash', 'dashboard.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'dashboard.actions.delete_selected', 'dashboard.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('orderKey');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
