<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Elastica\Client;
use Elasticsearch\Endpoints\Indices\Create;
use Elasticsearch\Endpoints\Indices\Exists;
use Elasticsearch\Endpoints\Indices\PutAlias;
use Elasticsearch\Endpoints\Indices\PutMapping;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Psr\Log\LoggerInterface;

class Mapping
{
    final public const string FINALIZATION_DATETIME_FIELD = '_finalization_datetime';
    final public const string FINALIZED_BY_FIELD = '_finalized_by';
    final public const string HASH_FIELD = '_sha1';
    final public const string SIGNATURE_FIELD = '_signature';
    final public const string CONTENT_TYPE_FIELD = '_contenttype';
    final public const string VERSION_UUID = '_version_uuid';
    final public const string VERSION_TAG = '_version_tag';

    final public const array MAPPING_INTERNAL_FIELDS = [
        Mapping::PUBLISHED_DATETIME_FIELD => Mapping::PUBLISHED_DATETIME_FIELD,
        Mapping::FINALIZATION_DATETIME_FIELD => Mapping::FINALIZATION_DATETIME_FIELD,
        Mapping::FINALIZED_BY_FIELD => Mapping::FINALIZED_BY_FIELD,
        Mapping::HASH_FIELD => Mapping::HASH_FIELD,
        Mapping::SIGNATURE_FIELD => Mapping::SIGNATURE_FIELD,
        Mapping::CONTENT_TYPE_FIELD => Mapping::CONTENT_TYPE_FIELD,
        Mapping::VERSION_UUID => Mapping::VERSION_UUID,
        Mapping::VERSION_TAG => Mapping::VERSION_TAG,
    ];

    final public const string CONTENT_TYPE_META_FIELD = 'content_type';
    final public const string GENERATOR_META_FIELD = 'generator';
    final public const string GENERATOR_META_FIELD_VALUE = 'elasticms';
    final public const string CORE_VERSION_META_FIELD = 'core_version';
    final public const string INSTANCE_ID_META_FIELD = 'instance_id';
    final public const string PUBLISHED_DATETIME_FIELD = '_published_datetime';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Client $elasticaClient,
        private readonly FieldTypeType $fieldTypeType,
        private readonly ElasticsearchService $elasticsearchService,
        private readonly ElasticaService $elasticaService,
        private readonly string $instanceId,
        private readonly string $dynamicMapping,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function generateMapping(ContentType $contentType): array
    {
        $out = [
            'properties' => [],
            'dynamic' => $this->dynamicMapping,
        ];

        if (null != $contentType->getFieldType()) {
            $out['properties'] = $this->fieldTypeType->generateMapping($contentType->getFieldType());
        }

        $this->addCopyToAllField($out['properties']);
        $out['properties'] = \array_merge(['_all' => ['type' => 'text']], $out['properties']);
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

        return $out;
    }

    /**
     * @return array<mixed>
     */
    public function dataFieldToArray(DataField $dataField): array
    {
        return $this->fieldTypeType->dataFieldToArray($dataField);
    }

    /**
     * @param array<mixed> $mapping1
     * @param array<mixed> $mapping2
     *
     * @return array<mixed>
     */
    private function mergeMappings(array $mapping1, array $mapping2): array
    {
        $mapping = \array_merge($mapping1, $mapping2);
        foreach ($mapping as $name => $fields) {
            if (isset($fields['properties']) && isset($mapping1[$name]) && isset($mapping1[$name]['properties'])) {
                $mapping[$name]['properties'] = $this->mergeMappings($fields['properties'], $mapping1[$name]['properties']);
            }
        }

        return $mapping;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getMapping(Environment ...$environments): ?array
    {
        $mergeMapping = [];
        foreach ($environments as $environment) {
            try {
                $mappings = $this->elasticaService->getIndex($environment->getAlias())->getMapping();

                if (isset($mappings['properties'])) {
                    $mergeMapping = $this->mergeMappings($mappings['properties'], $mergeMapping);
                    continue;
                }

                foreach ($mappings as $mapping) {
                    $mergeMapping = $this->mergeMappings($mapping['properties'], $mergeMapping);
                }
            } catch (\Throwable) {
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
        $putAliasEndpoint = new PutAlias();
        $putAliasEndpoint->setIndex($indexName);
        $putAliasEndpoint->setName($aliasName);

        return $this->elasticaClient->requestEndpoint($putAliasEndpoint)->isOk();
    }

    public function putMapping(ContentType $contentType, string $indexes): bool
    {
        $body = $this->generateMapping($contentType);
        $endpoint = new PutMapping();
        $endpoint->setIndex($indexes);
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
    public function updateMapping(string $name, array $mappings): void
    {
        $endpoint = new PutMapping();
        $endpoint->setIndex($name);
        $endpoint->setBody($mappings);
        $this->elasticaClient->requestEndpoint($endpoint);
    }

    /** @param array<string, array<string, mixed>> $properties */
    private function addCopyToAllField(array &$properties): void
    {
        foreach ($properties as &$options) {
            if (\in_array($options['type'] ?? null, ['text', 'keyword'], true)) {
                $options['copy_to'] = \array_unique(\array_merge(['_all'], $options['copy_to'] ?? []));
                continue;
            }
            if (isset($options['properties'])) {
                $this->addCopyToAllField($options['properties']);
            }
        }
    }
}
