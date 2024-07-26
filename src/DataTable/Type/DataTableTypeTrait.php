<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Form\Data\TableAbstract;

use function Symfony\Component\Translation\t;

trait DataTableTypeTrait
{
    public function addColumnsOrderLabelName(TableAbstract $table): void
    {
        $table
            ->setDefaultOrder('orderKey')
            ->setLabelAttribute('label');

        $table->addColumn(t('key.loop_count', [], 'emsco-core'), 'orderKey');
        $table->addColumn(t('field.label', [], 'emsco-core'), 'label');
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
    }
}
