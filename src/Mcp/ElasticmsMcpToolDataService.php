<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\MultiplexedTabContainerFieldType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Json;
use Mcp\Exception\ToolCallException;
use Mcp\Server\Builder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class ElasticmsMcpToolDataService extends AbstractElasticmsMcpToolService
{
    public function __construct(
        UserService $userService,
        private ContentTypeService $contentTypeService,
        private RevisionService $revisionService,
        private DataService $dataService,
        private FormRegistryInterface $formRegistry,
        private AuthorizationCheckerInterface $authorizationChecker,
        private ElasticaService $elasticaService,
        LoggerInterface $logger,
        LoggerInterface $auditLogger,
    ) {
        parent::__construct($userService, $logger, $auditLogger);
    }

    /**
     * @return array{contentType: string, ouuid: string, revisionId: int, draft: bool, archived: bool, label: ?string, rawData: array<mixed>}
     */
    public function getDocument(string $contentType, string $ouuid): array
    {
        $toolName = \sprintf('get_document_%s', $contentType);

        return $this->wrapToolCall($toolName, [
            'content_type' => $contentType,
            'ouuid' => $ouuid,
        ], function () use ($contentType, $ouuid): array {
            $resolvedContentType = $this->contentTypeService->getByName($contentType);
            if (false === $resolvedContentType) {
                throw new ToolCallException(\sprintf('Content type "%s" was not found.', $contentType));
            }

            if (!$this->authorizationChecker->isGranted($resolvedContentType->role(ContentTypeRoles::VIEW))) {
                throw new ToolCallException(\sprintf('View access is not granted for content type "%s".', $contentType));
            }

            $revision = $this->revisionService->get($ouuid, $resolvedContentType->getName());
            if (!$revision instanceof Revision) {
                throw new ToolCallException(\sprintf('Content "%s" was not found for content type "%s".', $ouuid, $contentType));
            }

            return [
                'contentType' => $resolvedContentType->getName(),
                'ouuid' => $revision->giveOuuid(),
                'revisionId' => $revision->getId(),
                'draft' => $revision->isDraft(),
                'archived' => $revision->isArchived(),
                'label' => $revision->getLabel(),
                'rawData' => $revision->getRawData(),
            ];
        });
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array{contentType: string, ouuid: ?string, revisionId: int, draft: bool, archived: bool, rawData: array<mixed>}
     */
    public function createDocument(string $contentType, array $rawData = [], ?string $ouuid = null, bool $finalize = false): array
    {
        $toolName = \sprintf('create_document_%s', $contentType);

        return $this->wrapToolCall($toolName, [
            'content_type' => $contentType,
            'ouuid' => $ouuid,
            'raw_data_keys' => \array_map('strval', \array_keys($rawData)),
        ], function () use ($rawData, $ouuid, $contentType, $finalize): array {
            $resolvedContentType = $this->contentTypeService->getByName($contentType);
            if (false === $resolvedContentType) {
                throw new ToolCallException(\sprintf('Content type "%s" was not found.', $contentType));
            }

            try {
                $resolvedContentType->validate();
            } catch (\RuntimeException $exception) {
                throw new ToolCallException($exception->getMessage(), 0, $exception);
            }

            if (!$this->authorizationChecker->isGranted($resolvedContentType->role(ContentTypeRoles::CREATE))) {
                throw new ToolCallException(\sprintf('Create access is not granted for content type "%s".', $contentType));
            }

            try {
                $this->dataService->hasCreateRights($resolvedContentType);
                $revision = $this->dataService->newDocument($resolvedContentType, $ouuid, $rawData);

                if ($finalize) {
                    $revision->autoSaveToRawData();
                    $revision = $this->dataService->finalizeDraft($revision);
                }
            } catch (\Throwable $exception) {
                throw new ToolCallException($exception->getMessage(), 0, $exception);
            }

            return [
                'contentType' => $resolvedContentType->getName(),
                'ouuid' => $revision->getOuuid(),
                'revisionId' => $revision->getId(),
                'draft' => $revision->isDraft(),
                'archived' => $revision->isArchived(),
                'rawData' => $revision->getRawData(),
            ];
        });
    }

    public function addGetDocumentTools(Builder $builder): void
    {
        foreach ($this->contentTypeService->getAll() as $contentType) {
            if (!$this->isViewableContentType($contentType)) {
                continue;
            }

            $contentTypeName = $contentType->getName();

            $builder->addTool(
                handler: fn (string $ouuid): array => $this->getDocument($contentTypeName, $ouuid),
                name: \sprintf('get_document_%s', $contentTypeName),
                description: \sprintf('Read the current content revision for the %s content type indexed in the %s environment.', $contentTypeName, $contentType->giveEnvironment()->getName()),
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'ouuid' => ['type' => 'string'],
                    ],
                    'required' => ['ouuid'],
                    'additionalProperties' => false,
                ],
                outputSchema: $this->buildGetDocumentOutputSchema($contentType),
            );
        }
    }

    public function addCreateDocumentTools(Builder $builder): void
    {
        foreach ($this->contentTypeService->getAll() as $contentType) {
            if (!$this->isCreatableContentType($contentType)) {
                continue;
            }

            $contentTypeName = $contentType->getName();

            $builder->addTool(
                handler: fn (array $rawData = [], ?string $ouuid = null, bool $finalize = false): array => $this->createDocument($contentTypeName, $rawData, $ouuid, $finalize),
                name: \sprintf('create_document_%s', $contentTypeName),
                description: \sprintf('Create a new document in the %s content type indexed in the %s environment.', $contentTypeName, $contentType->giveEnvironment()->getName()),
                inputSchema: $this->buildCreateDocumentInputSchema($contentType),
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'contentType' => ['type' => 'string'],
                        'ouuid' => ['type' => 'string'],
                        'revisionId' => ['type' => 'integer'],
                        'draft' => ['type' => 'boolean'],
                        'rawData' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'required' => ['contentType', 'ouuid', 'revisionId', 'draft', 'rawData'],
                    'additionalProperties' => false,
                ],
            );
        }
    }

    /**
     * @param array<mixed>|string $search
     *
     * @return array<mixed>
     */
    public function search(array|string $search): array
    {
        return $this->wrapToolCall('search', [], function () use ($search): array {
            if (\is_array($search)) {
                $search = Json::encode($search);
            }

            $searchObject = Search::deserialize($search);
            $resultSet = $this->elasticaService->search($searchObject);

            return $resultSet->getResponse()->getData();
        });
    }

    public function addSearchTool(Builder $builder): void
    {
        $builder->addTool(
            handler: fn (array|string $search): array => $this->search($search),
            name: 'search',
            description: 'Execute an Elasticsearch search query against the elasticMS indices. Accepts the same search payload as the elasticMS REST API (/api/search).',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'search' => [
                        'oneOf' => [
                            [
                                'type' => 'object',
                                'description' => 'The serialized Search object as a JSON object (indices, query, size, from, sort, contentTypes, etc.).',
                                'additionalProperties' => true,
                            ],
                            [
                                'type' => 'string',
                                'description' => 'The serialized Search object as a JSON string.',
                            ],
                        ],
                    ],
                ],
                'required' => ['search'],
                'additionalProperties' => false,
            ],
            outputSchema: [
                'type' => 'object',
                'additionalProperties' => true,
            ],
        );
    }

    private function isViewableContentType(ContentType $contentType): bool
    {
        return $contentType->giveEnvironment()->getManaged()
            && $contentType->isActive()
            && $this->authorizationChecker->isGranted($contentType->role(ContentTypeRoles::VIEW));
    }

    private function isCreatableContentType(ContentType $contentType): bool
    {
        return $contentType->giveEnvironment()->getManaged()
            && $contentType->isActive()
            && $this->authorizationChecker->isGranted($contentType->role(ContentTypeRoles::CREATE));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGetDocumentOutputSchema(ContentType $contentType): array
    {
        $rawDataSchema = $this->buildRawDataSchema($contentType->getFieldType(), filterEditableFields: false, includeRequired: false);
        $rawDataSchema['additionalProperties'] = true;

        return [
            'type' => 'object',
            'properties' => [
                'contentType' => ['type' => 'string'],
                'ouuid' => ['type' => 'string'],
                'revisionId' => ['type' => 'integer'],
                'draft' => ['type' => 'boolean'],
                'archived' => ['type' => 'boolean'],
                'label' => [
                    'type' => ['string', 'null'],
                ],
                'rawData' => $rawDataSchema,
            ],
            'required' => ['contentType', 'ouuid', 'revisionId', 'draft', 'archived', 'rawData'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateDocumentInputSchema(ContentType $contentType): array
    {
        $rawDataSchema = $this->buildRawDataSchema($contentType->getFieldType(), filterEditableFields: true, includeRequired: true);
        $rawDataSchema['additionalProperties'] = true;

        return [
            'type' => 'object',
            'properties' => [
                'rawData' => $rawDataSchema,
                'ouuid' => [
                    'type' => 'string',
                    'description' => 'Optional OUUID. When omitted, elasticMS will generate one.',
                ],
                'finalize' => [
                    'type' => 'boolean',
                    'description' => 'If set to true, the document will be finalized directly in the content type default environment. If set to false or omitted, the document will remain a draft in progress.',
                ],
            ],
            'required' => ['rawData'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRawDataSchema(FieldType $rootFieldType, bool $filterEditableFields = true, bool $includeRequired = true): array
    {
        return $this->buildObjectSchemaFromChildren($rootFieldType->getValidChildren(), $filterEditableFields, $includeRequired);
    }

    /**
     * @param FieldType[] $fieldTypes
     *
     * @return array<string, mixed>
     */
    private function buildObjectSchemaFromChildren(array $fieldTypes, bool $filterEditableFields = true, bool $includeRequired = true): array
    {
        $properties = [];
        $required = [];

        foreach ($fieldTypes as $fieldType) {
            $this->appendFieldSchema($fieldType, $properties, $required, $filterEditableFields, $includeRequired);
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];

        if ($includeRequired && [] !== $required) {
            $schema['required'] = \array_values(\array_unique($required));
        }

        return $schema;
    }

    /**
     * @param array<mixed>       $properties
     * @param array<int, string> $required
     */
    private function appendFieldSchema(FieldType $fieldType, array &$properties, array &$required, bool $filterEditableFields = true, bool $includeRequired = true): void
    {
        if ($fieldType->isDeleted() || ($filterEditableFields && !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()))) {
            return;
        }

        $fieldTypeClass = $fieldType->getType();

        if ($fieldTypeClass::isVirtual($fieldType->getOptions())) {
            if (MultiplexedTabContainerFieldType::class === $fieldTypeClass) {
                $schema = $this->buildFieldSchema($fieldType, $filterEditableFields, $includeRequired);
                foreach ($schema['properties'] ?? [] as $propertyName => $propertySchema) {
                    $properties[$propertyName] = $propertySchema;
                }

                return;
            }

            foreach ($fieldType->getValidChildren() as $childFieldType) {
                $this->appendFieldSchema($childFieldType, $properties, $required, $filterEditableFields, $includeRequired);
            }

            return;
        }

        $properties[$fieldType->getName()] = $this->buildFieldSchema($fieldType, $filterEditableFields, $includeRequired);

        if ($includeRequired && (bool) $fieldType->getRestrictionOption('mandatory', false)) {
            $required[] = $fieldType->getName();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFieldSchema(FieldType $fieldType, bool $filterEditableFields = true, bool $includeRequired = true): array
    {
        $schema = $this->getDataFieldType($fieldType)->generateJsonSchema($fieldType, fn (array $fieldTypes): array => $this->buildObjectSchemaFromChildren($fieldTypes, $filterEditableFields, $includeRequired));

        $schema['title'] ??= (string) $fieldType->getDisplayOption('label', $fieldType->getName());
        $description = $fieldType->getExtraOption('description', $fieldType->getDescription());

        if (\is_string($description) && '' !== \trim($description)) {
            $schema['description'] = $description;
        }

        return $schema;
    }

    private function getDataFieldType(FieldType $fieldType): DataFieldType
    {
        $innerType = $this->formRegistry->getType($fieldType->getType())->getInnerType();

        if (!$innerType instanceof DataFieldType) {
            throw new \RuntimeException(\sprintf('Unexpected form type "%s" for field "%s".', $fieldType->getType(), $fieldType->getName()));
        }

        return $innerType;
    }
}
