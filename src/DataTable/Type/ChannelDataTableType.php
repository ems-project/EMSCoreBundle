<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\Channel\ChannelService;

class ChannelDataTableType extends AbstractEntityTableType
{
    public function __construct(ChannelService $entityService)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumn('channel.index.column.label', 'label');
        $column = $table->addColumn('channel.index.column.name', 'name');
        $column->setPathCallback(fn (Channel $channel, string $baseUrl = '') => $baseUrl.$channel->getEntryPath(), '_blank');
        $table->addColumn('channel.index.column.alias', 'alias');
        $table->addColumnDefinition(new BoolTableColumn('channel.index.column.public', 'public'));
        $table->addItemGetAction('ems_core_channel_edit', 'channel.actions.edit', 'pencil');
        $table->addItemPostAction('ems_core_channel_delete', 'channel.actions.delete', 'trash', 'channel.actions.delete_confirm');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'channel.actions.delete_selected', 'channel.actions.delete_selected_confirm');
        $table->setDefaultOrder('orderKey');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
