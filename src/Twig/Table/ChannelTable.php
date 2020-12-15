<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Table;

use EMS\CoreBundle\Entity\Channel;

final class ChannelTable implements TableInterface
{
    /**
     * @var Channel[]
     */
    private $channels;

    /**
     * @param Channel[] $channels
     */
    public function __construct(array $channels)
    {
        $this->channels = $channels;
    }

    public function getTitleTransKey(): string
    {
        return 'channel.index.title';
    }

    public function getAddTransKey(): string
    {
        return 'channel.add.title';
    }

    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @return \IteratorAggregate<string, ChannelRow>
     */
    public function getIterator(): iterable
    {
        foreach ($this->channels as $channel) {
            yield $channel->getId() => new ChannelRow($channel);
        }
    }

    public function count(): int
    {
        return \count($this->channels);
    }

    public function getColumns(): iterable
    {
        return [
            new TableColumn('channel.index.column.name', 'name'),
        ];
    }

    public function getActions(): iterable
    {
        return [
            TableAction::getAction('ems_core_channel_edit', 'channel', 'channel.actions.edit', 'pencil'),
            TableAction::postAction('ems_core_channel_delete', 'channel', 'channel.actions.delete', 'trash', 'channel.actions.delete_confirm'),
        ];
    }
}
