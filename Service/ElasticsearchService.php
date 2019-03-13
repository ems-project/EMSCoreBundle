<?php

namespace EMS\CoreBundle\Service;

class ElasticsearchService
{
    
    /**
     * The elasticsearch version as specified in the bundle parameter
     *
     * @var string
     */
    private $version;
    
    /**
     * Constructor
     *
     * @param string $version
     */
    public function __construct($version)
    {
        $this->version = $version;
        ;
    }
    
    /**
     * Returns the parameter specified version
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }
    
    /**
     * Compare the paramter specified version with a string
     *
     * @param string $version
     * @return mixed
     */
    public function compare($version)
    {
        return version_compare($this->version, $version);
    }

    /**
     * Return a keyword mapping (not analyzed)
     * @return string[]
     */
    public function getKeywordMapping()
    {
        if (version_compare($this->version, '5') > 0) {
            return [
                'type' => 'keyword',
            ];
        }
        return [
            'type' => 'string',
            'index' => 'not_analyzed'
        ];
    }



    /**
     * Convert mapping
     * @return string[]
     */
    public function convertMapping(array $in)
    {
        $out = $in;
        if (version_compare($this->version, '5') > 0) {
            if (isset($out['analyzer']) && $out['analyzer'] === 'keyword') {
                $out['type'] = 'keyword';
                unset($out['analyzer']);
                unset($out['fielddata']);
                unset($out['index']);
            } elseif (isset($out['index']) && $out['index'] === 'not_analyzed') {
                $out['type'] = 'keyword';
                unset($out['analyzer']);
                unset($out['fielddata']);
                unset($out['index']);
            } elseif (isset($out['type']) && $out['type'] === 'string') {
                $out['type'] = 'text';
            } elseif (isset($out['type']) && $out['type'] === 'keyword') {
                unset($out['analyzer']);
                unset($out['fielddata']);
                unset($out['index']);
            }
        }
        return $out;
    }


    /**
     * Return a keyword mapping (not analyzed)
     * @return string[]
     */
    public function updateMapping($mapping)
    {

        if (isset($mapping['copy_to']) && !empty($mapping['copy_to']) && is_string($mapping['copy_to'])) {
            $mapping['copy_to'] = explode(',', $mapping['copy_to']);
        }

        if (version_compare($this->version, '5') > 0) {
            if ($mapping['type'] === 'string') {
                if ((isset($mapping['analyzer']) && $mapping['analyzer'] === 'keyword') || (empty($mapping['analyzer']) && isset($mapping['index']) && $mapping['index'] === 'not_analyzed')) {
                    $mapping['type'] = 'keyword';
                    unset($mapping['analyzer']);
                } else {
                    $mapping['type'] = 'text';
                }
            }

            if (isset($mapping['index']) && $mapping['index'] === 'No') {
                $mapping['index'] = false;
            }
            if (isset($mapping['index']) && $mapping['index'] !== false) {
                $mapping['index'] = true;
            }
        }
        return $mapping;
    }
    
    /**
     * Return a datetime mapping
     * @return string[]
     */
    public function getDateTimeMapping()
    {
        return [
            'type' => 'date',
            'format' => 'date_time_no_millis'
        ];
    }

    /**
     * Return a not indexed text mapping
     * @return string[]
     */
    public function getNotIndexedStringMapping()
    {
        if (version_compare($this->version, '5') > 0) {
            return [
                'type' => 'text',
                'index' => false,
            ];
        }
        return [
            'type' => 'string',
            'index' => 'no'
        ];
    }

    /**
     * Return a indexed text mapping
     * @return string[]
     */
    public function getIndexedStringMapping()
    {
        if (version_compare($this->version, '5') > 0) {
            return [
                'type' => 'text',
                'index' => true,
            ];
        }
        return [
            'type' => 'string',
            'index' => 'analyzed'
        ];
    }

    /**
     * Return a indexed text mapping
     * @return string[]
     */
    public function getLongMapping()
    {
        return [
            "type" => "long",
        ];
    }

    public function withAllMapping()
    {
        return version_compare($this->version, '5.6') < 0;
    }
}
