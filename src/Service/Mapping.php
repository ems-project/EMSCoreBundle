<?php

namespace EMS\CoreBundle\Service;

use Elastica\Client as ElasticaClient;
use Elastica\Exception\ResponseException;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;

class Mapping
{
    /** @var string */
    const FINALIZATION_DATETIME_FIELD = '_finalization_datetime';
    /** @var string */
    const FINALIZED_BY_FIELD = '_finalized_by';
    /** @var string */
    const HASH_FIELD = '_sha1';
    /** @var string */
    const SIGNATURE_FIELD = '_signature';
    /** @var string */
    const CONTENT_TYPE_FIELD = '_contenttype';
    /** @var string */
    const VERSION_UUID = '_version_uuid';
    /** @var string */
    const VERSION_TAG = '_version_tag';
    /** @var string */
    /** @var array<string, string> */
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
    /** @var string */
    const CONTENT_TYPE_META_FIELD = 'content_type';
    /** @var string */
    const GENERATOR_META_FIELD = 'generator';
    /** @var string */
    const GENERATOR_META_FIELD_VALUE = 'elasticms';
    /** @var string */
    const CORE_VERSION_META_FIELD = 'core_version';
    /** @var string */
    const INSTANCE_ID_META_FIELD = 'instance_id';
    /** @var string */
    const PUBLISHED_DATETIME_FIELD = '_published_datetime';

    /** @var EnvironmentService */
    private $environmentService;
    /** @var FieldTypeType $fieldTypeType */
    private $fieldTypeType;
    /** @var ElasticsearchService $elasticsearchService */
    private $elasticsearchService;
    /** @var string*/
    private $instanceId;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var ElasticaClient */
    private $elasticaClient;

    /**
     * Constructor
     *
     * @param FieldTypeType $fieldTypeType
     * @param ElasticsearchService $elasticsearchService
     */
    public function __construct(ElasticaClient $elasticaClient, EnvironmentService $environmentService, FieldTypeType $fieldTypeType, ElasticsearchService $elasticsearchService, ElasticaService $elasticaService, $instanceId)
    {
        $this->elasticaClient = $elasticaClient;
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

        $elasticsearchVersion = $this->elasticaService->getVersion();
        if (\version_compare($elasticsearchVersion, '7.0') >= 0) {
            return $out;
        }
        
        return [ $this->getTypeName($contentType->getName()) => $out ];
    }

    public function getTypeName(string $contentTypeName): string
    {
        $version = $this->elasticaService->getVersion();
        if (\version_compare($version, '7.0') >= 0) {
            return '_doc';
        }
        if (\version_compare($version, '6.0') >= 0) {
            return 'doc';
        }
        return $contentTypeName;
    }

    public function getTypePath(string $contentTypeName): string
    {
        $version = $this->elasticaService->getVersion();
        if (\version_compare($version, '7.0') >= 0) {
            return '.';
        }
        return $this->getTypeName($contentTypeName);
    }

    public function dataFieldToArray(DataField $dataField)
    {
        return $this->fieldTypeType->dataFieldToArray($dataField);
    }

    public function getMapping(array $environmentNames): ?array
    {
        $mergeMapping = [];
        foreach ($environmentNames as $environmentName) {
            try {
                $environment = $this->environmentService->getByName($environmentName);
                if ($environment === false) {
                    continue;
                }
                $mappings = $this->elasticaClient->getIndex($environment->getAlias())->getMapping();

                if (isset($mappings['_meta']) && isset($mappings['properties'])) {
                    $mergeMapping = \array_merge_recursive($mappings['properties'], $mergeMapping);
                    continue;
                }

                foreach ($mappings as $mapping) {
                    $mergeMapping = \array_merge_recursive($mapping['properties'], $mergeMapping);
                }
            } catch (ResponseException $e) {
                continue;
            }
        }
        return $mergeMapping;
    }
}
