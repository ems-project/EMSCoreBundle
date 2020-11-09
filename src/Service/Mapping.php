<?php

namespace EMS\CoreBundle\Service;

use Elasticsearch\Client;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;

class Mapping
{
    const PUBLISHED_DATETIME_FIELD = '_published_datetime';
    const FINALIZATION_DATETIME_FIELD = '_finalization_datetime';
    const FINALIZED_BY_FIELD = '_finalized_by';
    const HASH_FIELD = '_sha1';
    const SIGNATURE_FIELD = '_signature';
    const CONTENT_TYPE_FIELD = '_contenttype';
    const VERSION_UUID = '_version_uuid';
    const VERSION_TAG = '_version_tag';

    const MAPPING_INTERNAL_FIELDS = [
        Mapping::PUBLISHED_DATETIME_FIELD => Mapping::PUBLISHED_DATETIME_FIELD,
        Mapping::FINALIZATION_DATETIME_FIELD => Mapping::FINALIZATION_DATETIME_FIELD,
        Mapping::FINALIZED_BY_FIELD => Mapping::FINALIZED_BY_FIELD,
        Mapping::HASH_FIELD => Mapping::HASH_FIELD,
        Mapping::SIGNATURE_FIELD => Mapping::SIGNATURE_FIELD,
        Mapping::CONTENT_TYPE_FIELD => Mapping::CONTENT_TYPE_FIELD,
        Mapping::VERSION_UUID => Mapping::VERSION_UUID,
        Mapping::VERSION_TAG => Mapping::VERSION_TAG,
    ];

    const CONTENT_TYPE_META_FIELD = 'content_type';
    const GENERATOR_META_FIELD = 'generator';
    const GENERATOR_META_FIELD_VALUE = 'elasticms';
    const CORE_VERSION_META_FIELD = 'core_version';
    const INSTANCE_ID_META_FIELD = 'instance_id';

    /** @var Client */
    private $client;

    /** @var EnvironmentService */
    private $environmentService;

    /** @var FieldTypeType $fieldTypeType */
    private $fieldTypeType;
    
    /** @var ElasticsearchService $elasticsearchService */
    private $elasticsearchService;

    /** @var string*/
    private $coreVersion;

    /** @var string*/
    private $instanceId;

    /** @var ElasticaService */
    private $elasticaService;

    /**
     * Constructor
     *
     * @param FieldTypeType $fieldTypeType
     * @param ElasticsearchService $elasticsearchService
     */
    public function __construct(Client $client, EnvironmentService $environmentService, FieldTypeType $fieldTypeType, ElasticsearchService $elasticsearchService, ElasticaService $elasticaService, $instanceId)
    {
        $this->client = $client;
        $this->environmentService = $environmentService;
        $this->fieldTypeType = $fieldTypeType;
        $this->elasticsearchService = $elasticsearchService;
        $this->elasticaService = $elasticaService;
        $this->instanceId = $instanceId;
    }
    
    public function generateMapping(ContentType $contentType, $withPipeline = false)
    {
        $out = [
            "properties" => [],
        ];

        if ($this->elasticsearchService->withAllMapping()) {
            $out['_all'] = [
                "store" => true,
                "enabled" => true,
            ];
        }
        
        if (null != $contentType->getFieldType()) {
            $out['properties'] = $this->fieldTypeType->generateMapping($contentType->getFieldType(), $withPipeline);
        }
        
        $out['properties'] = array_merge(
            [
                Mapping::HASH_FIELD => $this->elasticsearchService->getKeywordMapping(),
                Mapping::SIGNATURE_FIELD => $this->elasticsearchService->getNotIndexedStringMapping(),
                Mapping::FINALIZED_BY_FIELD => $this->elasticsearchService->getKeywordMapping(),
                Mapping::CONTENT_TYPE_FIELD => $this->elasticsearchService->getKeywordMapping(),
                Mapping::FINALIZATION_DATETIME_FIELD => $this->elasticsearchService->getDateTimeMapping(),
                Mapping::PUBLISHED_DATETIME_FIELD => $this->elasticsearchService->getDateTimeMapping(),
            ],
            $out['properties']
        );

        if ($contentType->hasVersionTags()) {
            $out['properties'][Mapping::VERSION_UUID] = $this->elasticsearchService->getKeywordMapping();
            $out['properties'][Mapping::VERSION_TAG] = $this->elasticsearchService->getKeywordMapping();
        }

        $out['_meta'] = [
            Mapping::CONTENT_TYPE_META_FIELD => $contentType->getName(),
            Mapping::GENERATOR_META_FIELD => Mapping::GENERATOR_META_FIELD_VALUE,
            Mapping::CORE_VERSION_META_FIELD => $this->elasticaService->getVersion(),
            Mapping::INSTANCE_ID_META_FIELD => $this->instanceId,
        ];
        
        return [ $this->getTypeName($contentType->getName()) => $out ];
    }

    public function getTypeName(string $contentTypeName): string
    {
        $version = $this->elasticaService->getVersion();
        if (\version_compare($version, '6.0') >= 0) {
            return 'doc';
        }
        return $contentTypeName;
    }

    public function dataFieldToArray(DataField $dataField)
    {
        return $this->fieldTypeType->dataFieldToArray($dataField);
    }

    public function getMapping(array $environmentNames): ?array
    {
        $indices = $this->client->indices();
        $indexes = [];

        foreach ($environmentNames as $name) {
            $env = $this->environmentService->getByName($name);

            if ($env && $indices->exists(['index' => $env->getAlias()])) {
                $indexes[] = $env->getAlias();
            }
        }

        $result = [];

        if (empty($indexes)) {
            return $result;
        }

        $mappings = $indices->getMapping(['index' => $indexes]);

        foreach ($mappings as $index) {
            foreach ($index['mappings'] as $type => $mapping) {
                $result = \array_merge_recursive($mapping['properties'], $result);
            }
        }

        return $result;
    }
}
