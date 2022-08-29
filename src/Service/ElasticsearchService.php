<?php

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Service\ElasticaService;

class ElasticsearchService
{
    private ElasticaService $elasticaService;

    public function __construct(ElasticaService $elasticaService)
    {
        $this->elasticaService = $elasticaService;
    }

    public function getVersion(): string
    {
        return $this->elasticaService->getVersion();
    }

    /**
     * Compare the parameter specified version with a string.
     *
     * @return mixed
     */
    public function compare(string $version)
    {
        return \version_compare($this->getVersion(), $version);
    }

    /**
     * Return a keyword mapping (not analyzed).
     *
     * @return array<string, string>
     */
    public function getKeywordMapping(): array
    {
        if (\version_compare($this->getVersion(), '5') > 0) {
            return [
                'type' => 'keyword',
            ];
        }

        return [
            'type' => 'string',
            'index' => 'not_analyzed',
        ];
    }

    /**
     * @param array<mixed> $in
     *
     * @return string[]
     */
    public function convertMapping(array $in): array
    {
        $out = $in;
        if (\version_compare($this->getVersion(), '5') > 0) {
            if (isset($out['analyzer']) && 'keyword' === $out['analyzer']) {
                $out['type'] = 'keyword';
                unset($out['analyzer']);
                unset($out['fielddata']);
                unset($out['index']);
            } elseif (isset($out['index']) && 'not_analyzed' === $out['index']) {
                $out['type'] = 'keyword';
                unset($out['analyzer']);
                unset($out['fielddata']);
                unset($out['index']);
            } elseif (isset($out['type']) && 'string' === $out['type']) {
                $out['type'] = 'text';
            } elseif (isset($out['type']) && 'keyword' === $out['type']) {
                unset($out['analyzer']);
                unset($out['fielddata']);
                unset($out['index']);
            }
        }

        return $out;
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

        if (\version_compare($this->getVersion(), '5') > 0) {
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
     * @return array<string, string|bool>
     */
    public function getIndexedStringMapping(): array
    {
        if (\version_compare($this->getVersion(), '5') > 0) {
            return [
                'type' => 'text',
                'index' => true,
            ];
        }

        return [
            'type' => 'string',
            'index' => 'analyzed',
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

    /**
     * @return mixed
     */
    public function withAllMapping()
    {
        return \version_compare($this->getVersion(), '5.6') < 0;
    }
}
