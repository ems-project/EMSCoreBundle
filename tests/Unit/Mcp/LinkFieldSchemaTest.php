<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Mcp;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedLinkFieldType;
use EMS\CoreBundle\Form\DataField\SelectUserPropertyFieldType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\UserService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class LinkFieldSchemaTest extends TestCase
{
    public function testJsonMenuNestedLinkFieldSchemaUsesMultipleOption(): void
    {
        $fieldType = new JsonMenuNestedLinkFieldType(
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(FormRegistryInterface::class),
            $this->createStub(ElasticsearchService::class),
            $this->createStub(ElasticaService::class),
            $this->createStub(EnvironmentService::class),
            $this->createStub(Environment::class),
            $this->createStub(LoggerInterface::class),
        );

        self::assertSame(['type' => 'string'], $fieldType->generateMcpSchema(new FieldType(), static fn (array $fieldTypes): array => []));
        self::assertSame([
            'type' => 'array',
            'items' => ['type' => 'string'],
        ], $fieldType->generateMcpSchema(new FieldType()->setOptions(['displayOptions' => ['multiple' => true]]), static fn (array $fieldTypes): array => []));
    }

    public function testSelectUserPropertyFieldSchemaUsesMultipleOption(): void
    {
        $fieldType = new SelectUserPropertyFieldType(
            $this->createStub(UserService::class),
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(FormRegistryInterface::class),
            $this->createStub(ElasticsearchService::class),
        );

        self::assertSame(['type' => 'string'], $fieldType->generateMcpSchema(new FieldType(), static fn (array $fieldTypes): array => []));
        self::assertSame([
            'type' => 'array',
            'items' => ['type' => 'string'],
        ], $fieldType->generateMcpSchema(new FieldType()->setOptions(['displayOptions' => ['multiple' => true]]), static fn (array $fieldTypes): array => []));
    }

    public function testDataLinkFieldSchemaUsesMultipleOption(): void
    {
        $fieldType = new DataLinkFieldType(
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(FormRegistryInterface::class),
            $this->createStub(ElasticsearchService::class),
            $this->createStub(EventDispatcherInterface::class),
        );

        self::assertSame(['type' => 'string'], $fieldType->generateMcpSchema(new FieldType(), static fn (array $fieldTypes): array => []));
        self::assertSame([
            'type' => 'array',
            'items' => ['type' => 'string'],
        ], $fieldType->generateMcpSchema(new FieldType()->setOptions(['displayOptions' => ['multiple' => true]]), static fn (array $fieldTypes): array => []));
    }
}
