<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

class DataTableTypeCollection
{
    /**
     * @param iterable<DataTableTypeInterface> $types
     */
    public function __construct(private readonly iterable $types)
    {
    }

    public function getByClass(string $class): DataTableTypeInterface
    {
        foreach ($this->types as $type) {
            if ($type instanceof $class) {
                return $type;
            }
        }

        throw new \RuntimeException(\sprintf('Could not find dataTable type "%s"', $class));
    }

    public function getByHash(string $hash): DataTableTypeInterface
    {
        foreach ($this->types as $type) {
            if ($type->getHash() === $hash) {
                return $type;
            }
        }

        throw new \RuntimeException(\sprintf('Could not find dataTable type for hash "%s"', $hash));
    }
}
