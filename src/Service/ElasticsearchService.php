<?php

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Service\ElasticaService;

class ElasticsearchService
{
    public function __construct(private readonly ElasticaService $elasticaService)
    {
    }

    public function getVersion(): string
    {
        return $this->elasticaService->getVersion();
    }

    /**
     * Return a keyword mapping (not analyzed).
     *
     * @return array<string, string>
     */
    public function getKeywordMapping(): array
    {
        return ['type' => 'keyword'];
    }

    /**
     * Return a keyword mapping (not analyzed).
     *
     * @param array<mixed> $mapping
     *
     * @return array<mixed>
     */
    public function updateMapping(array $mapping): array
    {
        if (isset($mapping['copy_to']) && !empty($mapping['copy_to']) && \is_string($mapping['copy_to'])) {
            $mapping['copy_to'] = \explode(',', $mapping['copy_to']);
        }

        if ('string' === $mapping['type']) {
            if ('keyword' === ($mapping['analyzer'] ?? null) || (empty($mapping['analyzer']))) {
                $mapping['type'] = 'keyword';
                unset($mapping['analyzer']);
            } elseif ('version' === ($mapping['analyzer'] ?? null)) {
                $mapping['type'] = 'version';
                unset($mapping['analyzer']);
            } else {
                $mapping['type'] = 'text';
            }
        }

        return $mapping;
    }

    /**
     * @return array<string, string>
     */
    public function getDateTimeMapping(): array
    {
        return [
            'type' => 'date',
            'format' => 'date_time_no_millis',
        ];
    }

    /**
     * @return array{type: 'text', index: false}
     */
    public function getNotIndexedStringMapping(): array
    {
        return [
            'type' => 'text',
            'index' => false,
        ];
    }

    /**
     * @return array{type: 'text', index: true}
     */
    public function getIndexedStringMapping(): array
    {
        return [
            'type' => 'text',
            'index' => true,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getLongMapping(): array
    {
        return [
            'type' => 'long',
        ];
    }
}
