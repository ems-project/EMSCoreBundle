<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\TableAbstract;

use function Symfony\Component\Translation\t;

trait DataTableTypeTrait
{
    public function addColumnsOrderLabelName(TableAbstract $table): self
    {
        $table->setDefaultOrder('orderKey')->setLabelAttribute('label');

        $table->addColumn(t('key.loop_count', [], 'emsco-core'), 'orderKey');
        $table->addColumn(t('field.label', [], 'emsco-core'), 'label', 'label');
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name', 'name');

        return $this;
    }

    public function addColumnsCreatedModifiedDate(TableAbstract $table): self
    {
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_created', [], 'emsco-core'),
            attribute: 'created'
        ));
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_modified', [], 'emsco-core'),
            attribute: 'modified'
        ));

        return $this;
    }

    public function addItemEdit(TableAbstract $table, string $route): self
    {
        $table->addItemGetAction(
            route: $route,
            labelKey: t('action.edit', [], 'emsco-core'),
            icon: 'pencil'
        )->setButtonType('primary');

        return $this;
    }

    public function addItemDelete(TableAbstract $table, string $type, string $route): self
    {
        $table->addItemPostAction(
            route: $route,
            labelKey: t('action.delete', [], 'emsco-core'),
            icon: 'trash',
            messageKey: t('type.delete_confirm', ['type' => $type], 'emsco-core')
        )->setButtonType('outline-danger');

        return $this;
    }

    public function addTableToolbarActionAdd(TableAbstract $table, string $route): self
    {
        $table->addToolbarAction(
            label: t('action.add', [], 'emsco-core'),
            icon: 'fa fa-plus',
            routeName: $route
        )->setCssClass('btn btn-sm btn-primary');

        return $this;
    }

    public function addTableActionDelete(TableAbstract $table, string $type, string $name = TableAbstract::DELETE_ACTION): self
    {
        $table->addTableAction(
            name: $name,
            icon: 'fa fa-trash',
            labelKey: t('action.delete_selected', [], 'emsco-core'),
            confirmationKey: t('type.delete_selected_confirm', ['type' => $type], 'emsco-core')
        )->setCssClass('btn btn-sm btn-outline-danger');

        return $this;
    }
}
