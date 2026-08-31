<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Mcp;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UserInterface as CoreUserInterface;
use EMS\CoreBundle\Form\DataField\ContainerFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedEditorFieldType;
use EMS\CoreBundle\Form\DataField\MultiplexedTabContainerFieldType;
use EMS\CoreBundle\Form\DataField\NestedFieldType;
use EMS\CoreBundle\Form\DataField\TextStringFieldType;
use EMS\CoreBundle\Mcp\ElasticmsMcpToolDataService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class McpInputToRawDataTest extends TestCase
{
    public function testItConvertsJsonMenuNestedEditorArraysBackToElasticmsRawData(): void
    {
        $service = $this->createService();
        $contentType = $this->createPageContentType();

        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'mcpInputToRawData');
        $output = $method->invoke($service, $contentType, [
            'fr' => [
                'components' => [[
                    'id' => 'node-fr',
                    'type' => 'page',
                    'label' => 'Accueil',
                    'object' => [
                        'label' => 'Accueil',
                        'slug' => 'accueil',
                    ],
                    'children' => [],
                ]],
                'meta_title' => 'Meta title',
                'description' => 'Meta description',
            ],
            'nl' => [
                'components' => [[
                    'id' => 'node-nl',
                    'type' => 'page',
                    'label' => 'Home',
                    'object' => [
                        'label' => 'Home',
                        'slug' => 'home',
                    ],
                    'children' => [],
                ]],
                'meta_title' => 'Meta titel',
                'description' => 'Meta beschrijving',
            ],
        ]);

        self::assertIsString($output['fr']['components']);
        self::assertIsString($output['nl']['components']);
        self::assertSame('Accueil', Json::decode($output['fr']['components'])[0]['label']);
        self::assertSame('home', Json::decode($output['nl']['components'])[0]['object']['slug']);
        self::assertSame('Meta title', $output['fr']['meta_title']);
    }

    public function testSaveDocumentConvertsMcpInputBeforePersistingRawData(): void
    {
        $contentType = $this->createPageContentType();

        $contentTypeService = $this->createMock(ContentTypeService::class);
        $contentTypeService
            ->expects(self::once())
            ->method('getByName')
            ->with('page')
            ->willReturn($contentType);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $capturedRawData = null;
        $dataService = $this->createMock(DataService::class);
        $dataService->expects(self::once())
            ->method('hasCreateRights')
            ->with($contentType);
        $dataService->expects(self::once())
            ->method('newDocument')
            ->with(
                $contentType,
                null,
                self::callback(function (array $rawData) use (&$capturedRawData): bool {
                    $capturedRawData = $rawData;

                    return true;
                })
            )
            ->willReturnCallback(function (ContentType $contentType, ?string $ouuid, array $rawData): Revision {
                $rootDataField = new DataField();
                $rootDataField->addChild(new DataField()->setFieldType($contentType->getFieldType()->getValidChildren()[0]));

                $revision = new Revision()
                    ->setContentType($contentType)
                    ->setRawData($rawData)
                    ->setDataField($rootDataField)
                    ->setDraft(true)
                    ->setArchived(false)
                    ->setOuuid('generated-ouuid');

                $idProperty = new \ReflectionProperty(Revision::class, 'id');
                $idProperty->setValue($revision, 123);

                return $revision;
            });

        $service = $this->createService(
            contentTypeService: $contentTypeService,
            dataService: $dataService,
            authorizationChecker: $authorizationChecker,
        );

        $result = $service->saveDocument('page', [
            'fr' => [
                'components' => [[
                    'id' => 'node-fr',
                    'type' => 'page',
                    'label' => 'Accueil',
                    'object' => [
                        'label' => 'Accueil',
                        'slug' => 'accueil',
                    ],
                    'children' => [],
                ]],
                'meta_title' => 'Meta title',
                'description' => 'Meta description',
            ],
        ]);

        self::assertIsArray($capturedRawData);
        self::assertIsString($capturedRawData['fr']['components']);
        self::assertSame('Accueil', Json::decode($capturedRawData['fr']['components'])[0]['label']);
        self::assertIsArray($result['rawData']['fr']['components']);
        self::assertSame('accueil', $result['rawData']['fr']['components'][0]['object']['slug']);
    }

    public function testSaveDocumentInputSchemaHoistsJsonMenuDefinitionsAtRoot(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $service = $this->createService(authorizationChecker: $authorizationChecker);
        $method = new \ReflectionMethod(ElasticmsMcpToolDataService::class, 'buildSaveDocumentInputSchema');
        $schema = $method->invoke($service, $this->createPageContentType());

        $frRef = '#/$defs/properties__rawData__properties__fr__properties__components__jsonMenuNestedNode';
        $nlRef = '#/$defs/properties__rawData__properties__nl__properties__components__jsonMenuNestedNode';

        self::assertSame($frRef, $schema['properties']['rawData']['properties']['fr']['properties']['components']['items']['$ref']);
        self::assertSame($nlRef, $schema['properties']['rawData']['properties']['nl']['properties']['components']['items']['$ref']);
        self::assertArrayHasKey('properties__rawData__properties__fr__properties__components__jsonMenuNestedNode', $schema['$defs']);
        self::assertArrayHasKey('properties__rawData__properties__nl__properties__components__jsonMenuNestedNode', $schema['$defs']);
    }

    private function createService(
        ?ContentTypeService $contentTypeService = null,
        ?DataService $dataService = null,
        ?AuthorizationCheckerInterface $authorizationChecker = null,
    ): ElasticmsMcpToolDataService {
        $user = $this->createStub(CoreUserInterface::class);
        $user->method('getUsername')->willReturn('tester');
        $user->method('getUserIdentifier')->willReturn('tester');

        $userService = $this->createStub(UserService::class);
        $userService->method('getCurrentUser')->willReturn($user);

        return new ElasticmsMcpToolDataService(
            $userService,
            $contentTypeService ?? $this->createStub(ContentTypeService::class),
            $this->createStub(RevisionService::class),
            $dataService ?? $this->createStub(DataService::class),
            $this->createRegistry(),
            $authorizationChecker ?? $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(RouterInterface::class),
        );
    }

    private function createPageContentType(): ContentType
    {
        $labelField = new FieldType()->setName('label')->setType(TextStringFieldType::class);
        $slugField = new FieldType()->setName('slug')->setType(TextStringFieldType::class);
        $pageComponentField = new FieldType()->setName('page')->setType(NestedFieldType::class);
        $pageComponentField->addChild($labelField)->addChild($slugField);

        $componentsField = new FieldType()->setName('components')->setType(JsonMenuNestedEditorFieldType::class);
        $componentsField->addChild($pageComponentField);

        $metaTitleField = new FieldType()->setName('meta_title')->setType(TextStringFieldType::class);
        $descriptionField = new FieldType()->setName('description')->setType(TextStringFieldType::class);
        $seoField = new FieldType()->setName('seo')->setType(ContainerFieldType::class);
        $seoField->addChild($metaTitleField)->addChild($descriptionField);

        $localesField = new FieldType()
            ->setName('locales')
            ->setType(MultiplexedTabContainerFieldType::class)
            ->setOptions(['displayOptions' => ['values' => "fr\nnl"]]);
        $localesField->addChild($componentsField)->addChild($seoField);

        $rootField = new FieldType()->setName('source')->setType(NestedFieldType::class);
        $rootField->addChild($localesField);

        return new ContentType()
            ->setName('page')
            ->setActive(true)
            ->setEnvironment(new Environment()->setName('base')->setManaged(true))
            ->setFieldType($rootField);
    }

    private function createRegistry(): FormRegistryInterface
    {
        $registry = $this->createStub(FormRegistryInterface::class);
        $registry->method('getType')->willReturnCallback(function (string $name): ResolvedFormTypeInterface {
            $innerType = match ($name) {
                TextStringFieldType::class => new TextStringFieldType(
                    $this->createStub(AuthorizationCheckerInterface::class),
                    $this->createStub(FormRegistryInterface::class),
                    $this->createStub(ElasticsearchService::class),
                ),
                JsonMenuNestedEditorFieldType::class => new JsonMenuNestedEditorFieldType(
                    $this->createStub(AuthorizationCheckerInterface::class),
                    $this->createStub(FormRegistryInterface::class),
                    $this->createStub(ElasticsearchService::class),
                ),
                ContainerFieldType::class => new ContainerFieldType(
                    $this->createStub(AuthorizationCheckerInterface::class),
                    $this->createStub(FormRegistryInterface::class),
                    $this->createStub(ElasticsearchService::class),
                ),
                MultiplexedTabContainerFieldType::class => new MultiplexedTabContainerFieldType(
                    $this->createStub(AuthorizationCheckerInterface::class),
                    $this->createStub(FormRegistryInterface::class),
                    $this->createStub(ElasticsearchService::class),
                    $this->createStub(UserManager::class),
                    ['fr', 'nl'],
                ),
                default => throw new \RuntimeException(\sprintf('Unexpected type "%s"', $name)),
            };

            $resolvedType = $this->createStub(ResolvedFormTypeInterface::class);
            $resolvedType->method('getInnerType')->willReturn($innerType);

            return $resolvedType;
        });

        return $registry;
    }
}
