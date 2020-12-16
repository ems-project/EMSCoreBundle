<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Table;

use EMS\CoreBundle\Entity\Channel;

final class ChannelTable implements TableInterface
{
    /** @var string */
    public const DELETE_ACTION = 'delete';
    /**
     * @var Channel[]
     */
    private $channels;
    /**
     * @var string[]
     */
    private $selected = [];

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

    public function getAttributeName(): string
    {
        return 'channel';
    }

    public function getLabelAttribute(): string
    {
        return 'name';
    }

    public function count(): int
    {
        return \count($this->channels);
    }

    public function getColumns(): iterable
    {
        return [
            new TableColumn('channel.index.column.name', 'name'),
            new TableColumn('channel.index.column.slug', 'slug'),
            new TableColumn('channel.index.column.public', 'public'),
        ];
    }

    public function getItemActions(): iterable
    {
        return [
            TableItemAction::getAction('ems_core_channel_edit', 'channel.actions.edit', 'pencil'),
            TableItemAction::postAction('ems_core_channel_delete', 'channel.actions.delete', 'trash', 'channel.actions.delete_confirm'),
        ];
    }

    public function getTableActions(): iterable
    {
        return [
            new TableAction(self::DELETE_ACTION, 'fa fa-trash', 'channel.actions.delete_selected', 'channel.actions.delete_selected_confirm'),
        ];
    }

    /**
     * @return string[]
     */
    public function getSelected(): array
    {
        return $this->selected;
    }

    /**
     * @param string[] $selected
     */
    public function setSelected(array $selected): void
    {
        $this->selected = $selected;
    }
}
