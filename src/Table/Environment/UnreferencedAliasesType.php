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

    public function buildRows(): array
    {
        return [
            [1, 'test', 1, 5464, '-'],
            [2, 'test2', 1, 72444, '-'],
            [3, 'test3', 1, 154643, '-'],
            [4, 'test4', 1, 77588464, '-'],
            [5, 'test5', 1, 8885464, '-'],
            [1, 'test', 1, 5464, '-'],
            [2, 'test2', 1, 72444, '-'],
            [3, 'test3', 1, 154643, '-'],
            [4, 'test4', 1, 77588464, '-'],
            [5, 'test5', 1, 8885464, '-'],
            [1, 'test', 1, 5464, '-'],
            [2, 'test2', 1, 72444, '-'],
            [3, 'test3', 1, 154643, '-'],
            [4, 'test4', 1, 77588464, '-'],
            [5, 'test5', 1, 8885464, '-'],
            [1, 'test', 1, 5464, '-'],
            [2, 'test2', 1, 72444, '-'],
            [3, 'test3', 1, 154643, '-'],
            [4, 'test4', 1, 77588464, '-'],
            [5, 'test5', 1, 8885464, '-'],
            [1, 'test', 1, 5464, '-'],
            [2, 'test2', 1, 72444, '-'],
            [3, 'test3', 1, 154643, '-'],
            [4, 'test4', 1, 77588464, '-'],
            [5, 'test5', 1, 8885464, '-'],
            [1, 'test', 1, 5464, '-'],
            [2, 'test2', 1, 72444, '-'],
            [3, 'test3', 1, 154643, '-'],
            [4, 'test4', 1, 77588464, '-'],
            [5, 'test5', 1, 8885464, '-'],
        ];

    }

    public function getName(): string
    {
        return 'unreferenced_aliases';
    }
}