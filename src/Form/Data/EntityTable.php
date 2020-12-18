<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Service\EntityServiceInterface;

final class EntityTable extends TableAbstract
{
    /** @var EntityServiceInterface */
    private $entityService;
    /** @var int */
    private $size;
    /** @var int */
    private $from;

    public function __construct(EntityServiceInterface $entityService, int $from = 0, int $size = 50)
    {
        $this->entityService = $entityService;
        $this->from = $from;
        $this->size = $size;
    }

    public function isSortable(): bool
    {
        return $this->entityService->isSortable();
    }

    /**
     * @return \IteratorAggregate<string, EntityRow>
     */
    public function getIterator(): iterable
    {
        foreach ($this->entityService->get($this->from, $this->size) as $entity) {
            yield \strval($entity->getId()) => new EntityRow($entity);
        }
    }

    public function getAttributeName(): string
    {
        return $this->entityService->getEntityName();
    }

    public function count(): int
    {
        return $this->entityService->count();
    }

    public function getColumns(): iterable
    {
        return [
            new TableColumn('channel.index.column.name', 'name'),
            new TableColumn('channel.index.column.slug', 'slug'),
            new TableColumn('channel.index.column.public', 'public', [true => 'fa fa-check']),
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
}
