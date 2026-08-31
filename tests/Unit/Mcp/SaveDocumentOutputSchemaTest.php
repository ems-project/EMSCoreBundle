<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Mcp;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\CopyToFieldType;
use EMS\CoreBundle\Form\DataField\SubfieldType;
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

final class SaveDocumentOutputSchemaTest extends TestCase
{
    public function testOutputSchemaUsesRawDataSchemaAndArchivedFlag(): void
    {
        $rawDataSchema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
            ],
            'additionalProperties' => true,
        ];

        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'finalizeSaveDocumentOutputSchema');
        $schema = $method->invoke(null, $rawDataSchema);

        self::assertSame('boolean', $schema['properties']['archived']['type']);
        self::assertSame($rawDataSchema, $schema['properties']['rawData']);
        self::assertSame(['contentType', 'ouuid', 'revisionId', 'draft', 'archived', 'rawData', 'url'], $schema['required']);
    }

    public function testRawDataSchemaIgnoresMappingOnlyFields(): void
    {
        $titleField = new FieldType()->setName('title')->setType(TextStringFieldType::class);
        $copyToField = new FieldType()->setName('live_search')->setType(CopyToFieldType::class);
        $subfield = new FieldType()->setName('sortable')->setType(SubfieldType::class);
        $rootField = new FieldType()->setName('source');
        $rootField->addChild($titleField)->addChild($copyToField)->addChild($subfield);

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
                CopyToFieldType::class => new CopyToFieldType(
                    $authorizationChecker,
                    $this->createStub(FormRegistryInterface::class),
                    $this->createStub(ElasticsearchService::class),
                ),
                SubfieldType::class => new SubfieldType(
                    $authorizationChecker,
                    $this->createStub(FormRegistryInterface::class),
                    $this->createStub(ElasticsearchService::class),
                ),
                default => throw new \RuntimeException(\sprintf('Unexpected type "%s"', $name)),
            };

            $resolvedType = $this->createStub(ResolvedFormTypeInterface::class);
            $resolvedType->method('getInnerType')->willReturn($innerType);

            return $resolvedType;
        });

        $service = new ElasticmsMcpToolDataService(
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

        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'buildRawDataSchema');
        $schema = $method->invoke($service, $rootField, true, true);

        self::assertArrayHasKey('title', $schema['properties']);
        self::assertArrayNotHasKey('live_search', $schema['properties']);
        self::assertArrayNotHasKey('sortable', $schema['properties']);
    }
}
