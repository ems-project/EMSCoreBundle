<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Config;

class MediaLibraryConfigSort
{
    public function __construct(
        public readonly string $id,
        public readonly string $field,
        public readonly ?string $defaultOrder,
        public readonly ?string $nestedPath,
    ) {
    }

    /**
     * @return array<string, array{order: string, nested_path?: string}>
     */
    public function getQuery(string $order): array
    {
        $query = ['order' => $order];

        if ($this->nestedPath) {
            $query['nested_path'] = $this->nestedPath;
        }

        return [$this->field => $query];
    }

    public function getOrder(?string $sortOrder = null): string
    {
        return $sortOrder ?? ($this->defaultOrder ?? 'asc');
    }
}
