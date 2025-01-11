<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Mapping;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Mapping\FilterManager;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class FilterDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(FilterManager $filterManager)
    {
        parent::__construct($filterManager);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $this
            ->addColumnsOrderLabelName($table)
            ->addItemEdit($table, Routes::FILTER_EDIT);

        $table->addItemGetAction(
            route: Routes::FILTER_EXPORT,
            labelKey: t('action.export', [], 'emsco-core'),
            icon: 'sign-out'
        );

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemDelete($table, 'filter', Routes::FILTER_DELETE)
            ->addTableToolbarActionAdd($table, Routes::FILTER_ADD)
            ->addTableActionDelete($table, 'filter');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
