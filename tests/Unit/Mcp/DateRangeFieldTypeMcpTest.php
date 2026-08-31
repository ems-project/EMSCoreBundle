<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Mcp;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataField\DateRangeFieldType;
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

final class DateRangeFieldTypeMcpTest extends TestCase
{
    public function testDateRangeFieldSchemaUsesDateTimePropertiesWhenNested(): void
    {
        $fieldType = new FieldType()
            ->setName('period')
            ->setType(DateRangeFieldType::class)
            ->setOptions([
                'mappingOptions' => [
                    'nested' => true,
                    'fromDateMachineName' => 'start_at',
                    'toDateMachineName' => 'end_at',
                ],
            ]);

        $schema = $this->createDateRangeFieldType()->generateMcpSchema($fieldType, static fn (array $fieldTypes): array => []);

        self::assertSame('object', $schema['type']);
        self::assertSame('date-time', $schema['properties']['start_at']['format']);
        self::assertSame('date-time', $schema['properties']['end_at']['format']);
    }

    public function testVirtualDateRangeFieldSchemaIsFlattenedIntoRawDataSchema(): void
    {
        $fieldType = new FieldType()
            ->setName('period')
            ->setType(DateRangeFieldType::class)
            ->setOptions([
                'mappingOptions' => [
                    'nested' => false,
                    'fromDateMachineName' => 'start_at',
                    'toDateMachineName' => 'end_at',
                ],
            ]);
        $rootField = new FieldType()->setName('source');
        $rootField->addChild($fieldType);

        $service = $this->createService($fieldType);
        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'buildRawDataSchema');
        $schema = $method->invoke($service, $rootField, true, true);

        self::assertArrayHasKey('start_at', $schema['properties']);
        self::assertArrayHasKey('end_at', $schema['properties']);
        self::assertArrayNotHasKey('period', $schema['properties']);
        self::assertSame('date-time', $schema['properties']['start_at']['format']);
        self::assertSame('date-time', $schema['properties']['end_at']['format']);
    }

    public function testVirtualDateRangeFieldRawDataIsFlattenedAndNormalized(): void
    {
        $fieldType = new FieldType()
            ->setName('period')
            ->setType(DateRangeFieldType::class)
            ->setOptions([
                'mappingOptions' => [
                    'nested' => false,
                    'fromDateMachineName' => 'start_at',
                    'toDateMachineName' => 'end_at',
                ],
            ]);
        $rootField = new FieldType()->setName('source');
        $rootField->addChild($fieldType);

        $revision = new Revision()
            ->setRawData([
                'start_at' => '2026-01-01 10:00:00+00:00',
                'end_at' => '2026-01-02T11:30:00+00:00',
            ]);

        $service = $this->createService($fieldType);
        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'rawDataToMcpOutput');
        $output = $method->invoke($service, $revision);

        self::assertSame('2026-01-01T10:00:00+00:00', $output['start_at']);
        self::assertSame('2026-01-02T11:30:00+00:00', $output['end_at']);
    }

    public function testNestedDateRangeFieldRawDataIsNormalizedInPlace(): void
    {
        $fieldType = new FieldType()
            ->setName('period')
            ->setType(DateRangeFieldType::class)
            ->setOptions([
                'mappingOptions' => [
                    'nested' => true,
                    'fromDateMachineName' => 'start_at',
                    'toDateMachineName' => 'end_at',
                ],
            ]);
        $rootField = new FieldType()->setName('source');
        $rootField->addChild($fieldType);

        $revision = new Revision()
            ->setRawData([
                'period' => [
                    'start_at' => '2026-01-03 08:15:00+00:00',
                    'end_at' => '2026-01-04T09:45:00+00:00',
                ],
            ]);

        $service = $this->createService($fieldType);
        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'rawDataToMcpOutput');
        $output = $method->invoke($service, $revision);

        self::assertSame('2026-01-03T08:15:00+00:00', $output['period']['start_at']);
        self::assertSame('2026-01-04T09:45:00+00:00', $output['period']['end_at']);
    }

    private function createDateRangeFieldType(): DateRangeFieldType
    {
        return new DateRangeFieldType(
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(FormRegistryInterface::class),
            $this->createStub(ElasticsearchService::class),
        );
    }

    private function createService(FieldType $dateRangeField): ElasticmsMcpToolDataService
    {
        $dataService = $this->createStub(DataService::class);
        $dataService->method('loadDataStructure')->willReturnCallback(function (Revision $revision) use ($dateRangeField): void {
            $rootDataField = new DataField();
            $rootDataField->addChild(new DataField()->setFieldType($dateRangeField));
            $revision->setDataField($rootDataField);
        });

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $registry = $this->createStub(FormRegistryInterface::class);
        $registry->method('getType')->willReturnCallback(function (string $name): ResolvedFormTypeInterface {
            if (DateRangeFieldType::class !== $name) {
                throw new \RuntimeException(\sprintf('Unexpected type "%s"', $name));
            }

            $resolvedType = $this->createStub(ResolvedFormTypeInterface::class);
            $resolvedType->method('getInnerType')->willReturn(new DateRangeFieldType(
                $this->createStub(AuthorizationCheckerInterface::class),
                $this->createStub(FormRegistryInterface::class),
                $this->createStub(ElasticsearchService::class),
            ));

            return $resolvedType;
        });

        return new ElasticmsMcpToolDataService(
            $this->createStub(UserService::class),
            $this->createStub(ContentTypeService::class),
            $this->createStub(RevisionService::class),
            $dataService,
            $registry,
            $authorizationChecker,
            $this->createStub(LoggerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(RouterInterface::class),
        );
    }
}
