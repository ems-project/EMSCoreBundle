<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

abstract class TableAbstract implements TableInterface
{
    /** @var string */
    public const DELETE_ACTION = 'delete';

    /**
     * @var string[]
     */
    private $selected = [];
    /**
     * @var string[]
     */
    private $reordered = [];

    public function isSortable(): bool
    {
        return false;
    }

    public function getLabelAttribute(): string
    {
        return 'name';
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

    /**
     * @return string[]
     */
    public function getReordered(): array
    {
        return $this->reordered;
    }

    /**
     * @param string[] $reordered
     */
    public function setReordered(array $reordered): void
    {
        $this->reordered = $reordered;
    }
}
