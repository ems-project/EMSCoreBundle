<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Wysiwyg;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\WysiwygProfileService;

use function Symfony\Component\Translation\t;

class WysiwygProfileDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(WysiwygProfileService $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('orderKey')->setLabelAttribute('name');

        $table->addColumn(t('key.loop_count', [], 'emsco-core'), 'orderKey');
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name', 'name');

        $this
            ->addItemEdit($table, Routes::WYSIWYG_PROFILE_EDIT)
            ->addItemDelete($table, 'wysiwyg_profile', Routes::WYSIWYG_PROFILE_DELETE)
            ->addTableToolbarActionAdd($table, Routes::WYSIWYG_PROFILE_ADD)
            ->addTableActionDelete($table, 'wysiwyg_profile');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
