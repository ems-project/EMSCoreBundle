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
            if ((isset($mapping['analyzer']) && 'keyword' === $mapping['analyzer']) || (empty($mapping['analyzer']) && isset($mapping['index']) && 'not_analyzed' === $mapping['index'])) {
                $mapping['type'] = 'keyword';
                unset($mapping['analyzer']);
            } else {
                $mapping['type'] = 'text';
            }
        }

        if (isset($mapping['index']) && 'No' === $mapping['index']) {
            $mapping['index'] = false;
        }
        if (isset($mapping['index']) && false !== $mapping['index']) {
            $mapping['index'] = true;
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
