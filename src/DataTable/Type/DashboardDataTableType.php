<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\Dashboard\DashboardDefinition;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class DashboardDataTableType extends AbstractEntityTableType
{
    public function __construct(DashboardManager $entityService, private readonly string $templateNamespace)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('orderKey')->setLabelAttribute('label');

        $table->addColumn(t('key.loop_count', [], 'emsco-core'), 'orderKey');
        $columnLabel = $table->addColumn(t('field.label', [], 'emsco-core'), 'label');
        $columnLabel->setItemIconCallback(fn (Dashboard $dashboard) => $dashboard->getIcon());
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.type', [], 'emsco-core'),
            blockName: 'type',
            template: "@$this->templateNamespace/dashboard/columns.html.twig")
        );
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.definition', [], 'emsco-core'),
            blockName: 'definition',
            template: "@$this->templateNamespace/dashboard/columns.html.twig")
        );

        $table->addItemGetAction(
            route: Routes::DASHBOARD_ADMIN_EDIT,
            labelKey: t('action.edit', [], 'emsco-core'),
            icon: 'pencil'
        );

        $defineAction = $table->addItemActionCollection(t('action.define', [], 'emsco-core'), 'gear');
        foreach (DashboardDefinition::cases() as $dashboardDefinition) {
            $defineAction->addItemPostAction(
                route: Routes::DASHBOARD_ADMIN_DEFINE,
                labelKey: t('core.dashboard.define', ['define' => $dashboardDefinition->value], 'emsco-core'),
                icon: $dashboardDefinition->getIcon(),
                routeParameters: ['definition' => $dashboardDefinition->value]
            );
        }
        $defineAction->addItemPostAction(
            route: Routes::DASHBOARD_ADMIN_UNDEFINE,
            labelKey: t('core.dashboard.define', ['define' => null], 'emsco-core'),
            icon: 'eraser'
        );

        $table->addItemPostAction(
            route: Routes::DASHBOARD_ADMIN_DELETE,
            labelKey: t('action.delete', [], 'emsco-core'),
            icon: 'trash',
            messageKey: t('type.delete_confirm', ['type' => 'dashboard'], 'emsco-core')
        )->setButtonType('outline-danger');

        $table->addToolbarAction(
            label: t('action.add', [], 'emsco-core'),
            icon: 'fa fa-plus',
            routeName: Routes::DASHBOARD_ADMIN_ADD,
        );
        $table->addTableAction(
            name: TableAbstract::DELETE_ACTION,
            icon: 'fa fa-trash',
            labelKey: t('action.delete_selected', [], 'emsco-core'),
            confirmationKey: t('type.delete_selected_confirm', ['type' => 'dashboard'], 'emsco-core')
        )->setCssClass('btn btn-outline-danger');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
