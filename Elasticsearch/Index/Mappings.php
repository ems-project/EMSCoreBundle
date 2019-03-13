<?php

namespace EMS\CoreBundle\Elasticsearch\Index;

use EMS\CoreBundle\Service\Mapping as EMS;

class Mappings
{
    private $mappings = [];
    private $defaultProperties = [];

    public function __construct(array $languageAnalyzers = [])
    {
        foreach ($languageAnalyzers as $language => $analyzer) {
            $this->defaultProperties['all_'.$language] = [
                'type' => 'text',
                'store' => true,
                'analyzer' => $analyzer,
            ];
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->mappings);
    }

    public function toArray(): array
    {
        return $this->mappings;
    }

    public function add(string $name, array $mapping, ?string $type = 'doc'): Mappings
    {
        if (!isset($this->mappings[$type])) {
            $this->mappings[$type] = $this->getDefaults();
        }

        $this->mappings[$type]['properties'][$name] = $mapping;

        return $this;
    }

    private function getDefaults()
    {
        return [
            '_all' => ['store' => true, 'enabled' => true],
            'properties' => array_merge([
                EMS::CONTENT_TYPE_FIELD => ['type' => 'keyword'],
                EMS::HASH_FIELD => ['type' => 'keyword'],
            ], $this->defaultProperties)
        ];
    }
}
