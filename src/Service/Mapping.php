<?php

namespace EMS\CoreBundle\Service;

use Elastica\Client as ElasticaClient;
use Elasticsearch\Endpoints\Indices\Alias\Put;
use Elasticsearch\Endpoints\Indices\Create;
use Elasticsearch\Endpoints\Indices\Exists;
use Elasticsearch\Endpoints\Indices\Mapping\Put as MappingPut;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Psr\Log\LoggerInterface;

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
    /** @var FieldTypeType */
    private $fieldTypeType;
    /** @var ElasticsearchService */
    private $elasticsearchService;
    /** @var string */
    private $instanceId;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var ElasticaClient */
    private $elasticaClient;
    /** @var LoggerInterface */
    private $logger;
    /** @var bool */
    private $singleTypeIndex;

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger, ElasticaClient $elasticaClient, EnvironmentService $environmentService, FieldTypeType $fieldTypeType, ElasticsearchService $elasticsearchService, ElasticaService $elasticaService, string $instanceId, bool $singleTypeIndex)
    {
        $this->elasticaClient = $elasticaClient;
        $this->environmentService = $environmentService;
        $this->fieldTypeType = $fieldTypeType;
        $this->elasticsearchService = $elasticsearchService;
        $this->elasticaService = $elasticaService;
        $this->instanceId = $instanceId;
        $this->logger = $logger;
        $this->singleTypeIndex = $singleTypeIndex;
    }

    public function generateMapping(ContentType $contentType, $withPipeline = false)
    {
        $out = [
            'properties' => [],
        ];

        if ($this->elasticsearchService->withAllMapping()) {
            $out['_all'] = [
                'store' => true,
                'enabled' => true,
            ];
        }

        if (null != $contentType->getFieldType()) {
            $out['properties'] = $this->fieldTypeType->generateMapping($contentType->getFieldType(), $withPipeline);
        }

        $out['properties'] = \array_merge(
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
            Mapping::GENERATOR_META_FIELD => Mapping::GENERATOR_META_FIELD_VALUE,
            Mapping::CORE_VERSION_META_FIELD => $this->elasticaService->getVersion(),
            Mapping::INSTANCE_ID_META_FIELD => $this->instanceId,
        ];
        if ($this->singleTypeIndex) {
            $out['_meta'][Mapping::CONTENT_TYPE_META_FIELD] = $contentType->getName();
        }

        $elasticsearchVersion = $this->elasticaService->getVersion();
        if (\version_compare($elasticsearchVersion, '7.0') >= 0) {
            return $out;
        }

        return [$this->getTypeName($contentType->getName()) => $out];
    }

    public function getTypeName(string $contentTypeName): string
    {
        return $this->elasticaService->getTypeName($contentTypeName);
    }

    public function getTypePath(string $contentTypeName): string
    {
        return $this->elasticaService->getTypePath($contentTypeName);
    }

    public function dataFieldToArray(DataField $dataField)
    {
        return $this->fieldTypeType->dataFieldToArray($dataField);
    }

    /**
     * @param array<mixed> $mapping1
     * @param array<mixed> $mapping2
     *
     * @return array<mixed>
     */
    private function mergeMappings($mapping1, $mapping2): array
    {
        $mapping = \array_merge($mapping1, $mapping2);
        foreach ($mapping as $name => $fields) {
            if (isset($fields['properties']) && isset($mapping1[$name]) && isset($mapping1[$name]['properties'])) {
                $mapping[$name]['properties'] = $this->mergeMappings($mapping[$name]['properties'], $mapping1[$name]['properties']);
            }
        }

        return $mapping;
    }

    public function getMapping(array $environmentNames): ?array
    {
        $mergeMapping = [];
        foreach ($environmentNames as $environmentName) {
            try {
                $environment = $this->environmentService->getByName($environmentName);
                if (false === $environment) {
                    continue;
                }
                $mappings = $this->elasticaClient->getIndex($environment->getAlias())->getMapping();

                if (isset($mappings['_meta']) && isset($mappings['properties'])) {
                    $mergeMapping = $this->mergeMappings($mappings['properties'], $mergeMapping);
                    continue;
                }

                foreach ($mappings as $mapping) {
                    $mergeMapping = $this->mergeMappings($mapping['properties'], $mergeMapping);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $mergeMapping;
    }

    /**
     * @param array<mixed> $body
     */
    public function createIndex(string $indexName, array $body, ?string $aliasName = null): bool
    {
        $existsEndpoint = new Exists();
        $existsEndpoint->setIndex($indexName);
        $existResponse = $this->elasticaClient->requestEndpoint($existsEndpoint);
        if ($existResponse->isOk()) {
            return true;
        }

        $createEndpoint = new Create();
        $createEndpoint->setIndex($indexName);
        $createEndpoint->setBody($body);
        if (!$this->elasticaClient->requestEndpoint($createEndpoint)->isOk()) {
            return false;
        }

        if (null === $aliasName) {
            return true;
        }
        $putAliasEndpoint = new Put();
        $putAliasEndpoint->setIndex($indexName);
        $putAliasEndpoint->setName($aliasName);

        return $this->elasticaClient->requestEndpoint($putAliasEndpoint)->isOk();
    }

    public function putMapping(ContentType $contentType, string $indexes): bool
    {
        $body = $this->generateMapping($contentType, $contentType->getHavePipelines());
        $endpoint = new MappingPut();
        $endpoint->setIndex($indexes);
        $endpoint->setType($this->getTypePath($contentType->getName()));
        $endpoint->setBody($body);
        $result = $this->elasticaClient->requestEndpoint($endpoint);

        if (!$result->isOk()) {
            $this->logger->warning('service.contenttype.mappings_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                'environments' => $indexes,
                'elasticsearch_dump' => $result->getError(),
            ]);

            return false;
        }

        $this->logger->notice('service.contenttype.mappings_updated', [
            EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            'environments' => $indexes,
        ]);

        return true;
    }

    /**
     * @param array<mixed> $mappings
     */
    public function updateMapping(string $name, array $mappings, string $type): void
    {
        $endpoint = new MappingPut();
        $endpoint->setIndex($name);
        $endpoint->setBody($mappings);
        $endpoint->setType($this->getTypePath($type));
        $this->elasticaClient->requestEndpoint($endpoint);
    }
}
