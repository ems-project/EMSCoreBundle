<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class FormDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(FormManager $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $this
            ->addColumnsOrderLabelName($table)
            ->addItemEdit($table, Routes::FORM_ADMIN_EDIT);

        $table->addItemGetAction(
            route: Routes::FORM_ADMIN_REORDER,
            labelKey: t('action.reorder', [], 'emsco-core'),
            icon: 'reorder'
        );

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemDelete($table, 'form', Routes::FORM_ADMIN_DELETE)
            ->addTableToolbarActionAdd($table, Routes::FORM_ADMIN_ADD)
            ->addTableActionDelete($table, 'form');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
