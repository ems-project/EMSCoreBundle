<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Mcp;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataField\DateTimeFieldType;
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

final class DateTimeFieldTypeMcpTest extends TestCase
{
    public function testDateTimeFieldRawDataIsNormalizedToElasticsearchNoMillisFormat(): void
    {
        $fieldType = new FieldType()
            ->setName('published_at')
            ->setType(DateTimeFieldType::class);

        $revision = new Revision()
            ->setRawData([
                'published_at' => '2026-02-03T04:05:06+00:00',
            ]);

        $service = $this->createService($fieldType);
        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'rawDataToMcpOutput');
        $output = $method->invoke($service, $revision);

        self::assertSame('2026-02-03T04:05:06+00:00', $output['published_at']);
    }

    private function createService(FieldType $fieldType): ElasticmsMcpToolDataService
    {
        $dataService = $this->createStub(DataService::class);
        $dataService->method('loadDataStructure')->willReturnCallback(function (Revision $revision) use ($fieldType): void {
            $rootDataField = new DataField();
            $rootDataField->addChild(new DataField()->setFieldType($fieldType));
            $revision->setDataField($rootDataField);
        });

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $registry = $this->createStub(FormRegistryInterface::class);
        $registry->method('getType')->willReturnCallback(function (string $name): ResolvedFormTypeInterface {
            if (DateTimeFieldType::class !== $name) {
                throw new \RuntimeException(\sprintf('Unexpected type "%s"', $name));
            }

            $resolvedType = $this->createStub(ResolvedFormTypeInterface::class);
            $resolvedType->method('getInnerType')->willReturn(new DateTimeFieldType(
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
