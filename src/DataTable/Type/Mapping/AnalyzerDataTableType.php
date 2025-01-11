<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Mapping;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Mapping\AnalyzerManager;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class AnalyzerDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(AnalyzerManager $analyzerManager)
    {
        parent::__construct($analyzerManager);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $this
            ->addColumnsOrderLabelName($table)
            ->addItemEdit($table, Routes::ANALYZER_EDIT);

        $table->addItemGetAction(
            route: Routes::ANALYZER_EXPORT,
            labelKey: t('action.export', [], 'emsco-core'),
            icon: 'sign-out'
        );

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemDelete($table, 'analyzer', Routes::ANALYZER_DELETE)
            ->addTableToolbarActionAdd($table, Routes::ANALYZER_ADD)
            ->addTableActionDelete($table, 'analyzer');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
