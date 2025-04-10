<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\User\GroupManager;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class GroupDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(GroupManager $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $table->addColumn(t('field.label', [], 'emsco-core'), 'label');
        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addTableActionDelete($table, 'group')
            ->addItemEdit($table, Routes::GROUP_EDIT)
            ->addItemDelete($table, 'group', Routes::GROUP_DELETE)
            ->addTableToolbarActionAdd($table, Routes::GROUP_ADD);
    }
}
