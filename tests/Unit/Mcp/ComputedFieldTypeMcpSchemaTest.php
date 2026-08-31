<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Mcp;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\ComputedFieldType;
use EMS\CoreBundle\Form\DataField\TextStringFieldType;
use EMS\CoreBundle\Mcp\ElasticmsMcpToolDataService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ComputedFieldTypeMcpSchemaTest extends TestCase
{
    public function testComputedFieldInputSchemaIsIgnored(): void
    {
        $fieldType = new ComputedFieldType(
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(FormRegistryInterface::class),
            $this->createStub(ElasticsearchService::class),
            new Environment(new ArrayLoader()),
        );

        $schema = $fieldType->generateMcpSchema(new FieldType(), static fn (array $fieldTypes): array => [], false);

        self::assertSame([], $schema);
    }

    public function testComputedFieldOutputSchemaUsesDecodedExtraOption(): void
    {
        $fieldType = new ComputedFieldType(
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(FormRegistryInterface::class),
            $this->createStub(ElasticsearchService::class),
            new Environment(new ArrayLoader()),
        );

        $schema = $fieldType->generateMcpSchema(
            new FieldType()->setName('slug')->setOptions([
                'extraOptions' => [
                    'mcpOutputSchema' => '{{ {"type":"object","properties":{"slug":{"type":"string","const": fieldType.name}},"additionalProperties":false}|json_encode|raw }}',
                ],
            ]),
            static fn (array $fieldTypes): array => [],
            true,
        );

        self::assertSame('object', $schema['type']);
        self::assertSame('string', $schema['properties']['slug']['type']);
        self::assertSame('slug', $schema['properties']['slug']['const']);
        self::assertFalse($schema['additionalProperties']);
    }

    public function testBuildRawDataSchemaIgnoresComputedFieldForInputButKeepsItForOutput(): void
    {
        $titleField = new FieldType()->setName('title')->setType(TextStringFieldType::class);
        $slugField = new FieldType()
            ->setName('slug')
            ->setType(ComputedFieldType::class)
            ->setOptions([
                'extraOptions' => [
                    'mcpOutputSchema' => '{{ {"type":"object","properties":{"path":{"type":"string","const": fieldType.name}},"additionalProperties":false}|json_encode|raw }}',
                ],
            ]);
        $rootField = new FieldType()->setName('source');
        $rootField->addChild($titleField)->addChild($slugField);

        $service = $this->createService();
        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'buildRawDataSchema');

        $inputSchema = $method->invoke($service, $rootField, true, true, false);
        $outputSchema = $method->invoke($service, $rootField, false, false, true);

        self::assertArrayHasKey('title', $inputSchema['properties']);
        self::assertArrayNotHasKey('slug', $inputSchema['properties']);
        self::assertArrayHasKey('slug', $outputSchema['properties']);
        self::assertSame('object', $outputSchema['properties']['slug']['type']);
        self::assertSame('string', $outputSchema['properties']['slug']['properties']['path']['type']);
        self::assertSame('slug', $outputSchema['properties']['slug']['properties']['path']['const']);
    }

    private function createService(): ElasticmsMcpToolDataService
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $registry = $this->createStub(FormRegistryInterface::class);
        $registry->method('getType')->willReturnCallback(function (string $name) use ($authorizationChecker): ResolvedFormTypeInterface {
            $innerType = match ($name) {
                TextStringFieldType::class => new TextStringFieldType(
                    $authorizationChecker,
                    $this->createStub(FormRegistryInterface::class),
                    $this->createStub(ElasticsearchService::class),
                ),
                ComputedFieldType::class => new ComputedFieldType(
                    $authorizationChecker,
                    $this->createStub(FormRegistryInterface::class),
                    $this->createStub(ElasticsearchService::class),
                    new Environment(new ArrayLoader()),
                ),
                default => throw new \RuntimeException(\sprintf('Unexpected type "%s"', $name)),
            };

            $resolvedType = $this->createStub(ResolvedFormTypeInterface::class);
            $resolvedType->method('getInnerType')->willReturn($innerType);

            return $resolvedType;
        });

        return new ElasticmsMcpToolDataService(
            $this->createStub(UserService::class),
            $this->createStub(ContentTypeService::class),
            $this->createStub(RevisionService::class),
            $this->createStub(DataService::class),
            $registry,
            $authorizationChecker,
            $this->createStub(LoggerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(RouterInterface::class),
        );
    }
}
