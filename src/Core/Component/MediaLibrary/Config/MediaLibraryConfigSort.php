<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Config;

class MediaLibraryConfigSort
{
    public function __construct(
        public readonly string $id,
        public readonly string $field,
        public readonly ?string $defaultOrder,
        public readonly ?string $parentField,
    ) {
    }

    /**
     * @return array<string, array{order: string, nested?: array{path: string}}>
     */
    public function getQuery(string $order): array
    {
        $query = ['order' => $order];

        if ($this->parentField) {
            $query['nested'] = ['path' => $this->parentField];
        }

        return [$this->field => $query];
    }

    public function getOrder(?string $sortOrder = null): string
    {
        return $sortOrder ?? ($this->defaultOrder ?? 'asc');
    }
}
