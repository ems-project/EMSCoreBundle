<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Mcp\McpPromptService;

use function Symfony\Component\Translation\t;

class McpPromptDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(McpPromptService $entityService)
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
            ->addItemEdit($table, Routes::MCP_PROMPT_EDIT)
            ->addItemDelete($table, 'mcp_prompt', Routes::MCP_PROMPT_DELETE)
            ->addTableToolbarActionAdd($table, Routes::MCP_PROMPT_ADD)
            ->addTableActionDelete($table, 'mcp_prompt');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
