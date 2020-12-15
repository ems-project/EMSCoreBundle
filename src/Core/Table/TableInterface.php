<?php

namespace EMS\CoreBundle\Core\Table;

interface TableInterface
{
    /** @return string[] */
    public function getColumns(): array;

    public function addColumn(string $name): self;

    public function getName(): string;

    public function getTemplate(): string;
}