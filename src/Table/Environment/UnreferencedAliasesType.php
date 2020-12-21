<?php

namespace EMS\CoreBundle\Table\Environment;

use EMS\CoreBundle\Core\Table\TableInterface;
use EMS\CoreBundle\Core\Table\Type\AbstractType;
use EMS\CoreBundle\Service\AliasService;

final class UnreferencedAliasesType extends AbstractType
{
    /** @var AliasService */
    private $aliasSevice;

    public function __construct(AliasService $aliasService)
    {
        $this->aliasSevice = $aliasService;
    }

    public function buildTable(TableInterface $table, array $options): void
    {
        $table
            ->addColumn('#')
            ->addColumn('name')
            ->addColumn('indexes')
            ->addColumn('total')
            ->addColumn('action')
        ;
    }



    public function getName(): string
    {
        return 'unreferenced_aliases';
    }
}