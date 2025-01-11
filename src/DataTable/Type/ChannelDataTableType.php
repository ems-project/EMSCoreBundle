<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\Channel\ChannelService;

use function Symfony\Component\Translation\t;

class ChannelDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(ChannelService $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $this->addColumnsOrderLabelName($table);
        $table->getColumnByName('name')?->setPathCallback(
            pathCallback: fn (Channel $channel, string $baseUrl = '') => $baseUrl.$channel->getEntryPath(),
            target: '_blank'
        );

        $table->addColumn(t('field.alias', [], 'emsco-core'), 'alias');
        $table->addColumnDefinition(new BoolTableColumn(t('field.public_access', [], 'emsco-core'), 'public'));

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemEdit($table, 'ems_core_channel_edit')
            ->addItemDelete($table, 'channel', 'ems_core_channel_delete')
            ->addTableToolbarActionAdd($table, 'ems_core_channel_add')
            ->addTableActionDelete($table, 'channel');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}
