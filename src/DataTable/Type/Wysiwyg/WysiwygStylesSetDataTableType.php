<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Wysiwyg;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\WysiwygStylesSetService;

use function Symfony\Component\Translation\t;

class WysiwygStylesSetDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(WysiwygStylesSetService $entityService)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('orderKey')->setLabelAttribute('name');

        $table->addColumn(t('key.loop_count', [], 'emsco-core'), 'orderKey');
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name', 'name');

        $this
            ->addItemEdit($table, Routes::WYSIWYG_STYLE_SET_EDIT)
            ->addItemDelete($table, 'wysiwyg_style_set', Routes::WYSIWYG_STYLE_SET_DELETE)
            ->addTableToolbarActionAdd($table, Routes::WYSIWYG_STYLE_SET_ADD)
            ->addTableActionDelete($table, 'wysiwyg_style_set');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
