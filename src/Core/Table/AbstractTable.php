<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Table;

abstract class AbstractTable implements TableInterface
{
    /** @var string[] */
    protected $columns;

    /** @var array<mixed> */
    protected $rows;

    /** @var string */
    protected $name;
    /** @var array */
    protected $options;

    public function __construct(string $name, array $options)
    {
        $this->name = $name;
        $this->options = $options;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function setRows(array $rows): void
    {
        $this->rows = $rows;
    }



    public function addColumn(string $name): TableInterface
    {
        $this->columns[] = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }
}