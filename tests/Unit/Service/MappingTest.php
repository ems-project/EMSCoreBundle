<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Service;

use Elastica\Index;
use Elastica\Response;
use EMS\CommonBundle\Common\Log\LocalizedLogger;
use EMS\CommonBundle\Elasticsearch\Client;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\Mapping;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

#[AllowMockObjectsWithoutExpectations]
final class MappingTest extends TestCase
{
    public function testPutMappingAllowsDifferentFields(): void
    {
        $index = $this->createMock(Index::class);
        $index->method('getMapping')->willReturn([
            'properties' => [
                Mapping::HASH_FIELD => ['type' => 'keyword'],
                'legacy' => ['type' => 'keyword'],
            ],
        ]);
        $index->expects($this->once())
            ->method('setMapping')
            ->willReturn($this->successfulResponse());

        $mapping = $this->createMappingService($index, [
            'title' => [
                'type' => 'text',
                'fields' => ['keyword' => ['type' => 'keyword']],
            ],
        ]);

        self::assertTrue($mapping->putMapping($this->contentType(), 'content'));
    }

    public function testPutMappingRejectsChangedFieldType(): void
    {
        $index = $this->createMock(Index::class);
        $index->method('getMapping')->willReturn([
            'properties' => [
                'title' => ['type' => 'keyword'],
            ],
        ]);
        $index->expects($this->never())->method('setMapping');

        $mapping = $this->createMappingService($index, [
            'title' => ['type' => 'text'],
        ]);

        self::assertFalse($mapping->putMapping($this->contentType(), 'content'));
    }

    public function testPutMappingRejectsChangedSubfield(): void
    {
        $index = $this->createMock(Index::class);
        $index->method('getMapping')->willReturn([
            'properties' => [
                'title' => [
                    'type' => 'text',
                    'fields' => ['keyword' => ['type' => 'keyword']],
                ],
            ],
        ]);
        $index->expects($this->never())->method('setMapping');

        $mapping = $this->createMappingService($index, [
            'title' => [
                'type' => 'text',
                'fields' => ['keyword' => ['type' => 'text']],
            ],
        ]);

        self::assertFalse($mapping->putMapping($this->contentType(), 'content'));
    }

    public function testPutMappingReportsAllDifferences(): void
    {
        $index = $this->createMock(Index::class);
        $index->method('getMapping')->willReturn([
            'properties' => [
                'title' => ['type' => 'keyword'],
                'subtitle' => ['type' => 'integer'],
            ],
        ]);
        $index->expects($this->never())->method('setMapping');

        $logger = $this->createMock(LocalizedLogger::class);
        $differences = [];
        $logger->expects($this->exactly(2))
            ->method('messageWarning')
            ->with($this->callback(function (TranslatableMessage $message) use (&$differences): bool {
                $differences[] = $message->getParameters()['fieldPath'] ?? '';

                return true;
            }));

        $mapping = $this->createMappingService($index, [
            'title' => ['type' => 'text'],
            'subtitle' => ['type' => 'long'],
        ], $logger);

        self::assertFalse($mapping->putMapping($this->contentType(), 'content'));
        self::assertCount(2, $differences);
        self::assertStringContainsString('title', \implode(' ', $differences));
        self::assertStringContainsString('subtitle', \implode(' ', $differences));
    }

    /**
     * @param array<string, array<string, mixed>> $fieldMapping
     */
    private function createMappingService(Index $index, array $fieldMapping, ?LocalizedLogger $logger = null): Mapping
    {
        $client = $this->createMock(Client::class);
        $client->method('getIndex')->with('content')->willReturn($index);

        $fieldTypeType = $this->createMock(FieldTypeType::class);
        $fieldTypeType->method('generateMapping')->willReturn($fieldMapping);

        $elasticsearchService = $this->createMock(ElasticsearchService::class);
        $elasticsearchService->method('getKeywordMapping')->willReturn(['type' => 'keyword']);
        $elasticsearchService->method('getNotIndexedStringMapping')->willReturn(['type' => 'text', 'index' => false]);
        $elasticsearchService->method('getDateTimeMapping')->willReturn(['type' => 'date', 'format' => 'date_time_no_millis']);

        $elasticaService = $this->createMock(ElasticaService::class);
        $elasticaService->method('getVersion')->willReturn('test');

        return new Mapping(
            $logger ?? $this->createMock(LocalizedLogger::class),
            $client,
            $fieldTypeType,
            $elasticsearchService,
            $elasticaService,
            'instance',
            'false',
        );
    }

    private function contentType(): ContentType
    {
        $contentType = new ContentType();
        $contentType->setSingularName('Content');

        return $contentType;
    }

    private function successfulResponse(): Response
    {
        $response = $this->createMock(Response::class);
        $response->method('isOk')->willReturn(true);

        return $response;
    }
}
