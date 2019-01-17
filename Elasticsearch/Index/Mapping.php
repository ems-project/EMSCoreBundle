<?php

namespace EMS\CoreBundle\Elasticsearch\Index;

use EMS\CoreBundle\Service\Mapping as EMS;

class Mapping
{
    private $mappings = [];

    public function isEmpty(): bool
    {
        return empty($this->mappings);
    }

    public function toArray(): array
    {
        return $this->mappings;
    }

    public function add(string $name, array $mapping, string $type = '_doc'): Mapping
    {
        if (!isset($this->mappings[$type])) {
            $this->mappings[$type] = $this->getDefaults($type);
        }

        $this->mappings[$type][$name] = $mapping;

        return $this;
    }

    private function getDefaults(string $type)
    {
        return [
            '_all' => ['store' => true, 'enabled' => true],
            'properties' => [
                EMS::CONTENT_TYPE_FIELD => ['type' => 'keyword'],
                EMS::HASH_FIELD => ['type' => 'keyword'],
            ]
        ];
    }
}