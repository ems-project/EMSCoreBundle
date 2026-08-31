<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Mcp\McpToolService;

use function Symfony\Component\Translation\t;

class McpToolDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(McpToolService $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('name')->setLabelAttribute('label');

        $table->addColumn(t('field.label', [], 'emsco-core'), 'label');
        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $table->addColumnDefinition(new BoolTableColumn(t('field.enabled', [], 'emsco-core'), 'enabled'));

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemEdit($table, Routes::MCP_TOOL_EDIT)
            ->addItemDelete($table, 'mcp_tool', Routes::MCP_TOOL_DELETE)
            ->addTableToolbarActionAdd($table, Routes::MCP_TOOL_ADD)
            ->addTableActionDelete($table, 'mcp_tool');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
