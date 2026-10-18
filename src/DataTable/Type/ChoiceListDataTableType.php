<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\ChoiceList\ChoiceListManager;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class ChoiceListDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(ChoiceListManager $choiceListManager)
    {
        parent::__construct($choiceListManager);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->addColumn(t('field.label', [], 'emsco-core'), 'label', 'label');
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name', 'name');
        $table->addColumn(t('field.groups', [], 'emsco-core'), 'groupsCount', 'groupsCount');
        $table->addColumn(t('field.choices', [], 'emsco-core'), 'choicesCount', 'choicesCount');

        $this
            ->addItemEdit($table, Routes::CHOICE_LIST_ADMIN_EDIT)
            ->addColumnsCreatedModifiedDate($table)
            ->addItemDelete($table, 'choice_list', Routes::CHOICE_LIST_ADMIN_DELETE)
            ->addTableToolbarActionAdd($table, Routes::CHOICE_LIST_ADMIN_ADD)
            ->addTableActionDelete($table, 'choice_list');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
