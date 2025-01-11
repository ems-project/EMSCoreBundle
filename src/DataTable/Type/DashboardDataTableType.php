<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\Dashboard\DashboardDefinition;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class DashboardDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(DashboardManager $entityService, private readonly string $templateNamespace)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $this->addColumnsOrderLabelName($table);
        $table->getColumnByName('label')?->setItemIconCallback(fn (Dashboard $dashboard) => $dashboard->getIcon());

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.type', [], 'emsco-core'),
            blockName: 'dashboardType',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig")
        );
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.definition', [], 'emsco-core'),
            blockName: 'dashboardDefinition',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig")
        );

        $this->addItemEdit($table, Routes::DASHBOARD_ADMIN_EDIT);

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

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemDelete($table, 'dashboard', Routes::DASHBOARD_ADMIN_DELETE)
            ->addTableToolbarActionAdd($table, Routes::DASHBOARD_ADMIN_ADD)
            ->addTableActionDelete($table, 'dashboard');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
