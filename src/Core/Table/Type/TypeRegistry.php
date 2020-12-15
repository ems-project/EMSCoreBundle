<?php

namespace EMS\CoreBundle\Core\Table\Type;

final class TypeRegistry
{
    /** @var iterable<TypeInterface> */
    private $tableTypes;

    public function __construct(iterable $tables)
    {
        $this->tableTypes = $tables;
    }



    public function getByTypeClass(string $typeClass): TypeInterface
    {
        foreach ($this->tableTypes as $type) {
            if ($type instanceof $typeClass) {
                return $type;
            }
        }

        throw new \RuntimeException(sprintf('Table not found %s', $typeClass));
    }
}