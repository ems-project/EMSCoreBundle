<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\QuerySearchService;

class QuerySearchDataTableType extends AbstractEntityTableType
{
    public function __construct(QuerySearchService $entityService)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->addColumn('query_search.index.column.label', 'label');
        $table->addColumn('query_search.index.column.name', 'name');
        $table->addItemGetAction('ems_core_query_search_edit', 'query_search.actions.edit', 'pencil');
        $table->addItemPostAction('ems_core_query_search_delete', 'query_search.actions.delete', 'trash', 'query_search.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'query_search.actions.delete_selected', 'query_search.actions.delete_selected_confirm');
        $table->setDefaultOrder('label');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
