<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

final class ElasticmsMcpJsonSchema
{
    /** @var array<string, true> */
    private const array OBJECT_MAP_KEYS = [
        '$defs' => true,
        'definitions' => true,
        'dependentSchemas' => true,
        'patternProperties' => true,
        'properties' => true,
    ];

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $schema): array
    {
        $rootDefs = [];
        $schema = self::hoistLocalDefinitions($schema, [], $rootDefs);

        if ([] !== $rootDefs) {
            $schema['$defs'] = $rootDefs;
        }

        /** @var array<string, mixed> $normalized */
        $normalized = self::normalizeValue($schema);

        return $normalized;
    }

    /**
     * @param array<string, mixed>   $schema
     * @param array<int, string|int> $path
     * @param array<string, mixed>   $rootDefs
     *
     * @return array<string, mixed>
     */
    private static function hoistLocalDefinitions(array $schema, array $path, array &$rootDefs): array
    {
        $localDefs = \is_array($schema['$defs'] ?? null) ? $schema['$defs'] : [];
        if ([] !== $localDefs) {
            $refMap = [];
            foreach ($localDefs as $definitionName => $_definitionSchema) {
                if (!\is_string($definitionName)) {
                    continue;
                }

                $refMap[\sprintf('#/$defs/%s', $definitionName)] = \sprintf('#/$defs/%s', self::buildDefinitionName($path, $definitionName));
            }

            unset($schema['$defs']);
            $schema = self::rewriteReferences($schema, $refMap);

            foreach ($localDefs as $definitionName => $definitionSchema) {
                if (!\is_string($definitionName) || !\is_array($definitionSchema)) {
                    continue;
                }

                $uniqueDefinitionName = self::buildDefinitionName($path, $definitionName);
                $definitionSchema = self::rewriteReferences($definitionSchema, $refMap);
                $rootDefs[$uniqueDefinitionName] = self::hoistLocalDefinitions($definitionSchema, [...$path, '$defs', $definitionName], $rootDefs);
            }
        }

        foreach ($schema as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }

            $schema[$key] = self::hoistLocalDefinitions($value, [...$path, $key], $rootDefs);
        }

        return $schema;
    }

    /**
     * @param array<string, string> $refMap
     */
    private static function rewriteReferences(mixed $value, array $refMap): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $rewritten = [];
        foreach ($value as $key => $childValue) {
            if ('$ref' === $key && \is_string($childValue) && isset($refMap[$childValue])) {
                $rewritten[$key] = $refMap[$childValue];

                continue;
            }

            $rewritten[$key] = self::rewriteReferences($childValue, $refMap);
        }

        return $rewritten;
    }

    /**
     * @param array<int, string|int> $path
     */
    private static function buildDefinitionName(array $path, string $definitionName): string
    {
        if ([] === $path) {
            return $definitionName;
        }

        $parts = [];
        foreach ($path as $segment) {
            $normalized = \preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $segment);
            $normalized = \trim($normalized ?? '', '_');
            if ('' !== $normalized) {
                $parts[] = $normalized;
            }
        }

        $parts[] = $definitionName;

        return \implode('__', $parts);
    }

    private static function normalizeValue(mixed $value, ?string $key = null): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if ([] === $value && null !== $key && isset(self::OBJECT_MAP_KEYS[$key])) {
            return new \stdClass();
        }

        $normalized = [];
        foreach ($value as $childKey => $childValue) {
            $normalized[$childKey] = self::normalizeValue($childValue, \is_string($childKey) ? $childKey : null);
        }

        return $normalized;
    }
}
