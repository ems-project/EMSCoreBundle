<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Mcp;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuEditorFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedEditorFieldType;
use EMS\CoreBundle\Service\ElasticsearchService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class JsonMenuSchemaTest extends TestCase
{
    public function testJsonMenuEditorFieldSchemaIsRecursiveAndDiscriminated(): void
    {
        $field = new FieldType()->setName('menu');
        $field->addChild($this->createComponentFieldType('paragraph', 'body'));
        $field->addChild($this->createComponentFieldType('grid', 'columns'));

        $schema = $this->createJsonMenuEditorFieldType()->generateMcpSchema($field, $this->buildObjectSchema(...));

        self::assertSame('array', $schema['type']);
        self::assertSame(['$ref' => '#/$defs/jsonMenuNode'], $schema['items']);
        self::assertArrayHasKey('$defs', $schema);
        self::assertArrayHasKey('jsonMenuNode', $schema['$defs']);
        self::assertCount(4, $schema['$defs']['jsonMenuNode']['anyOf']);

        $typeVariant = $schema['$defs']['jsonMenuNode']['anyOf'][0];
        self::assertSame('paragraph', $typeVariant['properties']['type']['const']);
        self::assertSame('string', $typeVariant['properties']['id']['type']);
        self::assertSame('string', $typeVariant['properties']['body']['type']);
        self::assertSame(['$ref' => '#/$defs/jsonMenuNode'], $typeVariant['properties']['children']['items']);

        $contentTypeVariant = $schema['$defs']['jsonMenuNode']['anyOf'][1];
        self::assertSame('paragraph', $contentTypeVariant['properties']['contentType']['const']);
    }

    public function testJsonMenuNestedEditorFieldSchemaIsRecursiveAndUsesObjectVariants(): void
    {
        $field = new FieldType()->setName('components');
        $field->addChild($this->createComponentFieldType('paragraph', 'body'));
        $field->addChild($this->createComponentFieldType('grid', 'columns'));

        $schema = $this->createJsonMenuNestedEditorFieldType()->generateMcpSchema($field, $this->buildObjectSchema(...));

        self::assertSame('array', $schema['type']);
        self::assertSame(['$ref' => '#/$defs/jsonMenuNestedNode'], $schema['items']);
        self::assertArrayHasKey('$defs', $schema);
        self::assertArrayHasKey('jsonMenuNestedNode', $schema['$defs']);
        self::assertCount(2, $schema['$defs']['jsonMenuNestedNode']['anyOf']);

        $paragraphVariant = $schema['$defs']['jsonMenuNestedNode']['anyOf'][0];
        self::assertSame('paragraph', $paragraphVariant['properties']['type']['const']);
        self::assertSame('object', $paragraphVariant['properties']['object']['type']);
        self::assertSame('string', $paragraphVariant['properties']['object']['properties']['body']['type']);
        self::assertSame(['$ref' => '#/$defs/jsonMenuNestedNode'], $paragraphVariant['properties']['children']['items']);
    }

    /**
     * @param array<FieldType> $fieldTypes
     *
     * @return array<string, mixed>
     */
    public function buildObjectSchema(array $fieldTypes): array
    {
        $properties = [];

        foreach ($fieldTypes as $fieldType) {
            $properties[$fieldType->getName()] = ['type' => 'string'];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];
    }

    private function createJsonMenuEditorFieldType(): JsonMenuEditorFieldType
    {
        return new JsonMenuEditorFieldType(
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(FormRegistryInterface::class),
            $this->createStub(ElasticsearchService::class),
        );
    }

    private function createJsonMenuNestedEditorFieldType(): JsonMenuNestedEditorFieldType
    {
        return new JsonMenuNestedEditorFieldType(
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(FormRegistryInterface::class),
            $this->createStub(ElasticsearchService::class),
        );
    }

    private function createComponentFieldType(string $name, string $childName): FieldType
    {
        $component = new FieldType()->setName($name);
        $component->addChild(new FieldType()->setName($childName));

        return $component;
    }
}
