<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Mcp;

use EMS\CoreBundle\Mcp\ElasticmsMcpJsonSchema;
use PHPUnit\Framework\TestCase;

final class ElasticmsMcpJsonSchemaTest extends TestCase
{
    public function testItHoistsNestedDefinitionsToRootAndRewritesReferences(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'rawData' => [
                    'type' => 'object',
                    'properties' => [
                        'fr' => [
                            'type' => 'object',
                            'properties' => [
                                'components' => $this->createComponentsSchema(),
                            ],
                        ],
                        'nl' => [
                            'type' => 'object',
                            'properties' => [
                                'components' => $this->createComponentsSchema(),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $normalized = ElasticmsMcpJsonSchema::normalize($schema);

        self::assertArrayNotHasKey('$defs', $normalized['properties']['rawData']['properties']['fr']['properties']['components']);
        self::assertArrayNotHasKey('$defs', $normalized['properties']['rawData']['properties']['nl']['properties']['components']);

        $frRef = '#/$defs/properties__rawData__properties__fr__properties__components__jsonMenuNestedNode';
        $nlRef = '#/$defs/properties__rawData__properties__nl__properties__components__jsonMenuNestedNode';

        self::assertSame(['$ref' => $frRef], $normalized['properties']['rawData']['properties']['fr']['properties']['components']['items']);
        self::assertSame(['$ref' => $nlRef], $normalized['properties']['rawData']['properties']['nl']['properties']['components']['items']);
        self::assertArrayHasKey('properties__rawData__properties__fr__properties__components__jsonMenuNestedNode', $normalized['$defs']);
        self::assertArrayHasKey('properties__rawData__properties__nl__properties__components__jsonMenuNestedNode', $normalized['$defs']);
        self::assertSame(
            ['$ref' => $frRef],
            $normalized['$defs']['properties__rawData__properties__fr__properties__components__jsonMenuNestedNode']['properties']['children']['items']
        );
        self::assertSame(
            ['$ref' => $nlRef],
            $normalized['$defs']['properties__rawData__properties__nl__properties__components__jsonMenuNestedNode']['properties']['children']['items']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createComponentsSchema(): array
    {
        return [
            'type' => 'array',
            'items' => ['$ref' => '#/$defs/jsonMenuNestedNode'],
            '$defs' => [
                'jsonMenuNestedNode' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'children' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/$defs/jsonMenuNestedNode'],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }
}
