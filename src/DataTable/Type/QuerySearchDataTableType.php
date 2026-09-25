<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\QuerySearchService;

use function Symfony\Component\Translation\t;

class QuerySearchDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(QuerySearchService $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('label')->setLabelAttribute('label');

        $table->addColumn(t('field.label', [], 'emsco-core'), 'label');
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $table->addColumnDefinition(new BoolTableColumn(t('field.is_default', [], 'emsco-core'), 'default', 'circle'));

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemEdit($table, Routes::ADMIN_QUERY_SEARCH_EDIT);

        $setAsDefault = $table->addItemPostAction(
            route: Routes::ADMIN_QUERY_SEARCH_SET_AS_DEFAULT,
            labelKey: t('action.set_as_default', ['type' => 'query_search'], 'emsco-core'),
            icon: 'check',
            messageKey: t('type.confirm', ['type' => 'set_as_default'], 'emsco-core'),
            attributes: ['data-testid' => 'btn-action-set-as-default']
        );
        $setAsDefault->setButtonType('primary');

        $this
            ->addItemDelete($table, 'query_search', Routes::ADMIN_QUERY_SEARCH_DELETE)
            ->addTableToolbarActionAdd($table, Routes::ADMIN_QUERY_SEARCH_ADD)
            ->addTableActionDelete($table, 'query_search');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
