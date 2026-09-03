<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\McpPrompt;
use EMS\CoreBundle\Entity\McpResource;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Form\DataField\ChoiceFieldType;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\MultiplexedTabContainerFieldType;
use EMS\CoreBundle\Form\DataField\NestedFieldType;
use EMS\CoreBundle\Form\DataField\TextStringFieldType;
use EMS\CoreBundle\Tests\Integration\App\Kernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class McpControllerTest extends WebTestCase
{
    private const string API_TOKEN = 'elasticms-mcp-token';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();

        $container = self::getContainer();
        $doctrine = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $entityManager = $doctrine->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testInitializeRequiresValidBearerToken(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
                'HTTP_HOST' => 'localhost',
            ],
            content: $this->jsonEncode($this->initializePayload())
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testToolsListExposesPerContentTypeCreateTools(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($payload)
        );

        self::assertResponseIsSuccessful();
        $response = $this->decodeResponse($this->client);
        $tools = $response['result']['tools'] ?? [];
        $toolNames = \array_map(static fn (array $tool): string => (string) $tool['name'], $tools);

        self::assertContains('get_current_user', $toolNames);
        self::assertContains('init_asset_upload', $toolNames);
        self::assertContains('upload_asset_chunk', $toolNames);
        self::assertContains('download_asset_chunk', $toolNames);
        self::assertContains('get_asset_info', $toolNames);
        self::assertContains('get_news', $toolNames);
        self::assertContains('save_news', $toolNames);
        self::assertContains('get_secret', $toolNames);
        self::assertNotContains('save_secret', $toolNames);

        $currentUserTool = \array_first(\array_filter($tools, static fn (array $tool): bool => 'get_current_user' === ($tool['name'] ?? null))) ?? null;
        self::assertIsArray($currentUserTool);
        self::assertStringContainsString('elasticMS user profile', (string) ($currentUserTool['description'] ?? ''));
        self::assertSame('object', $currentUserTool['outputSchema']['type'] ?? null);
        self::assertSame('object', $currentUserTool['outputSchema']['properties']['user']['type'] ?? null);
        self::assertSame('string', $currentUserTool['outputSchema']['properties']['user']['properties']['username']['type'] ?? null);

        $initAssetTool = \array_first(\array_filter($tools, static fn (array $tool): bool => 'init_asset_upload' === ($tool['name'] ?? null))) ?? null;
        self::assertIsArray($initAssetTool);
        self::assertSame(['sha1'], $initAssetTool['inputSchema']['properties']['algo']['enum'] ?? null);
        self::assertSame('sha1', $initAssetTool['inputSchema']['properties']['algo']['default'] ?? null);

        $downloadAssetTool = \array_first(\array_filter($tools, static fn (array $tool): bool => 'download_asset_chunk' === ($tool['name'] ?? null))) ?? null;
        self::assertIsArray($downloadAssetTool);
        self::assertSame(0, $downloadAssetTool['inputSchema']['properties']['offset']['default'] ?? null);
        self::assertSame(5_242_880, $downloadAssetTool['inputSchema']['properties']['length']['default'] ?? null);

        $createNewsTool = \array_first(\array_filter($tools, static fn (array $tool): bool => 'save_news' === ($tool['name'] ?? null))) ?? null;

        self::assertIsArray($createNewsTool);
        self::assertStringContainsString('`news` in the `preview` environment', (string) ($createNewsTool['description'] ?? ''));
        self::assertSame(['title'], $createNewsTool['inputSchema']['properties']['rawData']['required'] ?? null);
        self::assertSame('string', $createNewsTool['inputSchema']['properties']['rawData']['properties']['title']['type'] ?? null);
        self::assertSame('object', $createNewsTool['inputSchema']['properties']['rawData']['properties']['body']['type'] ?? null);
        self::assertSame('string', $createNewsTool['inputSchema']['properties']['rawData']['properties']['body']['properties']['summary']['type'] ?? null);
        self::assertArrayNotHasKey('translations', $createNewsTool['inputSchema']['properties']['rawData']['properties'] ?? []);
        self::assertSame('Nederlands', $createNewsTool['inputSchema']['properties']['rawData']['properties']['nl']['title'] ?? null);
        self::assertSame('object', $createNewsTool['inputSchema']['properties']['rawData']['properties']['nl']['type'] ?? null);
        self::assertSame(['title', 'summary', 'body'], $createNewsTool['inputSchema']['properties']['rawData']['properties']['nl']['required'] ?? null);
        self::assertSame('string', $createNewsTool['inputSchema']['properties']['rawData']['properties']['nl']['properties']['body']['type'] ?? null);
        self::assertSame('Français', $createNewsTool['inputSchema']['properties']['rawData']['properties']['fr']['title'] ?? null);
        self::assertSame('array', $createNewsTool['inputSchema']['properties']['rawData']['properties']['authors']['type'] ?? null);
        self::assertSame('string', $createNewsTool['inputSchema']['properties']['rawData']['properties']['authors']['items']['properties']['name']['type'] ?? null);
        self::assertSame(['draft', 'published'], $createNewsTool['inputSchema']['properties']['rawData']['properties']['status']['enum'] ?? null);
    }

    public function testToolsCallCanReturnCurrentUserAndCreateContentTypeDraft(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $currentUserPayload = [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_current_user',
                'arguments' => new \stdClass(),
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($currentUserPayload)
        );

        self::assertResponseIsSuccessful();
        $currentUserResponse = $this->decodeResponse($this->client);
        self::assertArrayHasKey('result', $currentUserResponse, $this->jsonEncode($currentUserResponse));
        $structuredUser = $currentUserResponse['result']['structuredContent']['user'] ?? null;
        self::assertIsArray($structuredUser);
        self::assertSame('mcp-user', $structuredUser['username'] ?? null);

        $createDraftPayload = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'save_news',
                'arguments' => [
                    'rawData' => [
                        'title' => 'MCP News Draft',
                    ],
                ],
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($createDraftPayload)
        );

        self::assertResponseIsSuccessful();
        $createDraftResponse = $this->decodeResponse($this->client);
        $structuredDraft = $createDraftResponse['result']['structuredContent'] ?? null;

        self::assertIsArray($structuredDraft);
        self::assertSame('news', $structuredDraft['contentType'] ?? null);
        self::assertTrue($structuredDraft['draft'] ?? false);
        self::assertSame('MCP News Draft', $structuredDraft['rawData']['title'] ?? null);
        self::assertNotNull($structuredDraft['revisionId'] ?? null);
    }

    public function testCreateDocumentToolCanFinalizeDraft(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $createFinalizedPayload = [
            'jsonrpc' => '2.0',
            'id' => 41,
            'method' => 'tools/call',
            'params' => [
                'name' => 'save_news',
                'arguments' => [
                    'rawData' => [
                        'title' => 'MCP Finalized News',
                        'nl' => [
                            'title' => 'Titel NL',
                            'summary' => 'Samenvatting NL',
                            'body' => 'Body NL',
                        ],
                        'fr' => [
                            'title' => 'Titre FR',
                            'summary' => 'Resume FR',
                            'body' => 'Corps FR',
                        ],
                    ],
                    'finalize' => true,
                ],
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($createFinalizedPayload)
        );

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'));

        $createFinalizedResponse = $this->decodeResponse($this->client);
        self::assertArrayNotHasKey('error', $createFinalizedResponse);

        $result = $createFinalizedResponse['result'] ?? null;
        self::assertIsArray($result);

        $structuredRevision = $result['structuredContent'] ?? null;
        if (\is_array($structuredRevision)) {
            self::assertSame('news', $structuredRevision['contentType'] ?? null);
            self::assertFalse($structuredRevision['draft'] ?? true);
            self::assertFalse($structuredRevision['archived'] ?? true);
            self::assertSame('MCP Finalized News', $structuredRevision['rawData']['title'] ?? null);
            self::assertNotNull($structuredRevision['revisionId'] ?? null);

            return;
        }

        self::assertTrue($result['isError'] ?? false);
        self::assertSame('text', $result['content'][0]['type'] ?? null);
    }

    public function testAssetToolsCanUploadAndDownloadChunkedFile(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $content = 'first-chunk-'.\bin2hex(\random_bytes(8)).'-second-chunk';
        $firstChunk = \substr($content, 0, 12);
        $secondChunk = \substr($content, 12);
        $hash = \sha1($content);

        $initPayload = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'init_asset_upload',
                'arguments' => [
                    'hash' => $hash,
                    'size' => \strlen($content),
                    'name' => 'mcp-upload.txt',
                    'type' => 'text/plain',
                    'algo' => 'sha1',
                ],
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($initPayload)
        );

        self::assertResponseIsSuccessful();
        $initResponse = $this->decodeResponse($this->client);
        $initStructuredContent = $initResponse['result']['structuredContent'] ?? null;
        self::assertIsArray($initStructuredContent);
        self::assertSame($hash, $initStructuredContent['hash'] ?? null);
        self::assertFalse($initStructuredContent['available'] ?? true);

        foreach ([$firstChunk, $secondChunk] as $index => $chunk) {
            $uploadPayload = [
                'jsonrpc' => '2.0',
                'id' => 6 + $index,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'upload_asset_chunk',
                    'arguments' => [
                        'hash' => $hash,
                        'chunkBase64' => \base64_encode($chunk),
                    ],
                ],
            ];

            $this->client->request(
                Request::METHOD_POST,
                '/api/mcp',
                server: $this->mcpHeaders($sessionId),
                content: $this->jsonEncode($uploadPayload)
            );

            self::assertResponseIsSuccessful();
        }

        $uploadResponse = $this->decodeResponse($this->client);
        $uploadStructuredContent = $uploadResponse['result']['structuredContent'] ?? null;
        self::assertIsArray($uploadStructuredContent);
        self::assertTrue($uploadStructuredContent['available'] ?? false);
        self::assertSame(\strlen($content), $uploadStructuredContent['uploaded'] ?? null);

        $infoPayload = [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_asset_info',
                'arguments' => [
                    'hash' => $hash,
                ],
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($infoPayload)
        );

        self::assertResponseIsSuccessful();
        $infoResponse = $this->decodeResponse($this->client);
        $infoStructuredContent = $infoResponse['result']['structuredContent'] ?? null;
        self::assertIsArray($infoStructuredContent);
        self::assertSame('mcp-upload.txt', $infoStructuredContent['name'] ?? null);
        self::assertSame('text/plain', $infoStructuredContent['type'] ?? null);

        $downloadPayload = [
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/call',
            'params' => [
                'name' => 'download_asset_chunk',
                'arguments' => [
                    'hash' => $hash,
                    'offset' => 0,
                    'length' => \strlen($content),
                ],
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($downloadPayload)
        );

        self::assertResponseIsSuccessful();
        $downloadResponse = $this->decodeResponse($this->client);
        $downloadStructuredContent = $downloadResponse['result']['structuredContent'] ?? null;
        self::assertIsArray($downloadStructuredContent);
        self::assertSame($hash, $downloadStructuredContent['hash'] ?? null);
        self::assertSame(\strlen($content), $downloadStructuredContent['bytesRead'] ?? null);
        self::assertTrue($downloadStructuredContent['eof'] ?? false);
        self::assertSame($content, \base64_decode((string) ($downloadStructuredContent['chunkBase64'] ?? ''), true));
    }

    public function testResourcesExposeWysiwygStyleSetCssClasses(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $resourcesPayload = [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'resources/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($resourcesPayload)
        );

        self::assertResponseIsSuccessful();
        $resourcesResponse = $this->decodeResponse($this->client);
        $resources = $resourcesResponse['result']['resources'] ?? [];
        $wysiwygResource = \array_first(\array_filter($resources, static fn (array $resource): bool => 'wysiwyg_style_sets_css_classes' === ($resource['name'] ?? null))) ?? null;
        self::assertIsArray($wysiwygResource);
        self::assertSame('elasticms://wysiwyg-style-sets/css-classes', $wysiwygResource['uri'] ?? null);
        self::assertSame('application/json', $wysiwygResource['mimeType'] ?? null);

        $templatesPayload = [
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'resources/templates/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($templatesPayload)
        );

        self::assertResponseIsSuccessful();
        $templatesResponse = $this->decodeResponse($this->client);
        $templates = $templatesResponse['result']['resourceTemplates'] ?? [];
        $wysiwygTemplate = \array_first(\array_filter($templates, static fn (array $template): bool => 'wysiwyg_style_set_css_classes' === ($template['name'] ?? null))) ?? null;
        self::assertIsArray($wysiwygTemplate);
        self::assertSame('elasticms://wysiwyg-style-sets/{name}/css-classes', $wysiwygTemplate['uriTemplate'] ?? null);

        $readPayload = [
            'jsonrpc' => '2.0',
            'id' => 12,
            'method' => 'resources/read',
            'params' => [
                'uri' => 'elasticms://wysiwyg-style-sets/css-classes',
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($readPayload)
        );

        self::assertResponseIsSuccessful();
        $readResponse = $this->decodeResponse($this->client);
        $resourceText = $readResponse['result']['contents'][0]['text'] ?? null;
        self::assertIsString($resourceText);
        $resourceData = \json_decode($resourceText, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('bootstrap', $resourceData['styleSets'][0]['name'] ?? null);
        self::assertContains([
            'class' => 'btn-primary',
            'element' => 'a',
            'styleName' => 'Call-To-Action',
            'source' => 'config',
        ], $resourceData['styleSets'][0]['classes'] ?? []);
        self::assertContains([
            'class' => 'table-bordered',
            'element' => 'table',
            'styleName' => 'Table default',
            'source' => 'tableDefaultCss',
        ], $resourceData['styleSets'][0]['classes'] ?? []);

        $readStyleSetPayload = [
            'jsonrpc' => '2.0',
            'id' => 13,
            'method' => 'resources/read',
            'params' => [
                'uri' => 'elasticms://wysiwyg-style-sets/bootstrap/css-classes',
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($readStyleSetPayload)
        );

        self::assertResponseIsSuccessful();
        $readStyleSetResponse = $this->decodeResponse($this->client);
        $styleSetText = $readStyleSetResponse['result']['contents'][0]['text'] ?? null;
        self::assertIsString($styleSetText);
        $styleSetData = \json_decode($styleSetText, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('bootstrap', $styleSetData['name'] ?? null);
        self::assertContains([
            'class' => 'attention',
            'element' => 'div',
            'styleName' => 'Attention',
            'source' => 'config',
        ], $styleSetData['classes'] ?? []);
    }

    public function testResourcesExposeContentTypeDescriptionsWithoutHtml(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $resourcesPayload = [
            'jsonrpc' => '2.0',
            'id' => 14,
            'method' => 'resources/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($resourcesPayload)
        );

        self::assertResponseIsSuccessful();
        $resourcesResponse = $this->decodeResponse($this->client);
        $resources = $resourcesResponse['result']['resources'] ?? [];
        $contentTypeResource = \array_first(\array_filter($resources, static fn (array $resource): bool => 'content_types_descriptions' === ($resource['name'] ?? null))) ?? null;
        self::assertIsArray($contentTypeResource);
        self::assertSame('elasticms://content-types/descriptions', $contentTypeResource['uri'] ?? null);
        self::assertSame('application/json', $contentTypeResource['mimeType'] ?? null);

        $templatesPayload = [
            'jsonrpc' => '2.0',
            'id' => 15,
            'method' => 'resources/templates/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($templatesPayload)
        );

        self::assertResponseIsSuccessful();
        $templatesResponse = $this->decodeResponse($this->client);
        $templates = $templatesResponse['result']['resourceTemplates'] ?? [];
        $contentTypeTemplate = \array_first(\array_filter($templates, static fn (array $template): bool => 'content_type_description' === ($template['name'] ?? null))) ?? null;
        self::assertIsArray($contentTypeTemplate);
        self::assertSame('elasticms://content-types/{name}/description', $contentTypeTemplate['uriTemplate'] ?? null);

        $readPayload = [
            'jsonrpc' => '2.0',
            'id' => 16,
            'method' => 'resources/read',
            'params' => [
                'uri' => 'elasticms://content-types/descriptions',
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($readPayload)
        );

        self::assertResponseIsSuccessful();
        $readResponse = $this->decodeResponse($this->client);
        $resourceText = $readResponse['result']['contents'][0]['text'] ?? null;
        self::assertIsString($resourceText);
        $resourceData = \json_decode($resourceText, true, flags: \JSON_THROW_ON_ERROR);
        $newsContentType = \array_first(\array_filter($resourceData['contentTypes'] ?? [], static fn (array $contentType): bool => 'news' === ($contentType['name'] ?? null))) ?? null;
        self::assertIsArray($newsContentType);
        self::assertSame('News description & details', $newsContentType['description'] ?? null);
        self::assertSame('preview', $newsContentType['defaultEnvironment'] ?? null);

        $readContentTypePayload = [
            'jsonrpc' => '2.0',
            'id' => 17,
            'method' => 'resources/read',
            'params' => [
                'uri' => 'elasticms://content-types/news/description',
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($readContentTypePayload)
        );

        self::assertResponseIsSuccessful();
        $readContentTypeResponse = $this->decodeResponse($this->client);
        $contentTypeText = $readContentTypeResponse['result']['contents'][0]['text'] ?? null;
        self::assertIsString($contentTypeText);
        $contentTypeData = \json_decode($contentTypeText, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('news', $contentTypeData['name'] ?? null);
        self::assertSame('News description & details', $contentTypeData['description'] ?? null);
    }

    public function testResourcesExposeEnvironmentDescriptionsWithoutHtml(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $resourcesPayload = [
            'jsonrpc' => '2.0',
            'id' => 18,
            'method' => 'resources/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($resourcesPayload)
        );

        self::assertResponseIsSuccessful();
        $resourcesResponse = $this->decodeResponse($this->client);
        $resources = $resourcesResponse['result']['resources'] ?? [];
        $environmentResource = \array_first(\array_filter($resources, static fn (array $resource): bool => 'environments_descriptions' === ($resource['name'] ?? null))) ?? null;
        self::assertIsArray($environmentResource);
        self::assertSame('elasticms://environments/descriptions', $environmentResource['uri'] ?? null);
        self::assertSame('application/json', $environmentResource['mimeType'] ?? null);

        $templatesPayload = [
            'jsonrpc' => '2.0',
            'id' => 19,
            'method' => 'resources/templates/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($templatesPayload)
        );

        self::assertResponseIsSuccessful();
        $templatesResponse = $this->decodeResponse($this->client);
        $templates = $templatesResponse['result']['resourceTemplates'] ?? [];
        $environmentTemplate = \array_first(\array_filter($templates, static fn (array $template): bool => 'environment_description' === ($template['name'] ?? null))) ?? null;
        self::assertIsArray($environmentTemplate);
        self::assertSame('elasticms://environments/{name}/description', $environmentTemplate['uriTemplate'] ?? null);

        $readPayload = [
            'jsonrpc' => '2.0',
            'id' => 20,
            'method' => 'resources/read',
            'params' => [
                'uri' => 'elasticms://environments/descriptions',
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($readPayload)
        );

        self::assertResponseIsSuccessful();
        $readResponse = $this->decodeResponse($this->client);
        $resourceText = $readResponse['result']['contents'][0]['text'] ?? null;
        self::assertIsString($resourceText);
        $resourceData = \json_decode($resourceText, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('preview', $resourceData['environments'][0]['name'] ?? null);
        self::assertSame('Preview', $resourceData['environments'][0]['label'] ?? null);
        self::assertSame('Preview description & details', $resourceData['environments'][0]['description'] ?? null);

        $readEnvironmentPayload = [
            'jsonrpc' => '2.0',
            'id' => 21,
            'method' => 'resources/read',
            'params' => [
                'uri' => 'elasticms://environments/preview/description',
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($readEnvironmentPayload)
        );

        self::assertResponseIsSuccessful();
        $readEnvironmentResponse = $this->decodeResponse($this->client);
        $environmentText = $readEnvironmentResponse['result']['contents'][0]['text'] ?? null;
        self::assertIsString($environmentText);
        $environmentData = \json_decode($environmentText, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('preview', $environmentData['name'] ?? null);
        self::assertSame('Preview description & details', $environmentData['description'] ?? null);
    }

    public function testResourcesExposeCustomMcpResources(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $resourcesPayload = [
            'jsonrpc' => '2.0',
            'id' => 22,
            'method' => 'resources/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($resourcesPayload)
        );

        self::assertResponseIsSuccessful();
        $resourcesResponse = $this->decodeResponse($this->client);
        $resources = $resourcesResponse['result']['resources'] ?? [];
        $customResource = \array_first(\array_filter($resources, static fn (array $resource): bool => 'custom_site_info' === ($resource['name'] ?? null))) ?? null;
        self::assertIsArray($customResource);
        self::assertSame('elasticms://custom/site-info', $customResource['uri'] ?? null);
        self::assertSame('application/json', $customResource['mimeType'] ?? null);
        self::assertSame('Custom site info', $customResource['title'] ?? null);

        $readPayload = [
            'jsonrpc' => '2.0',
            'id' => 23,
            'method' => 'resources/read',
            'params' => [
                'uri' => 'elasticms://custom/site-info',
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($readPayload)
        );

        self::assertResponseIsSuccessful();
        $readResponse = $this->decodeResponse($this->client);
        $resourceText = $readResponse['result']['contents'][0]['text'] ?? null;
        self::assertIsString($resourceText);
        $resourceData = \json_decode($resourceText, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('custom_site_info', $resourceData['resourceName'] ?? null);
        self::assertSame('Custom site info', $resourceData['label'] ?? null);
    }

    public function testPromptsExposeCustomMcpPrompts(): void
    {
        $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $promptsPayload = [
            'jsonrpc' => '2.0',
            'id' => 24,
            'method' => 'prompts/list',
            'params' => new \stdClass(),
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($promptsPayload)
        );

        self::assertResponseIsSuccessful();
        $promptsResponse = $this->decodeResponse($this->client);
        $prompts = $promptsResponse['result']['prompts'] ?? [];
        $customPrompt = \array_first(\array_filter($prompts, static fn (array $prompt): bool => 'custom_summary' === ($prompt['name'] ?? null))) ?? null;
        self::assertIsArray($customPrompt);
        self::assertSame('Custom summary', $customPrompt['title'] ?? null);
        self::assertSame('Build a custom summary', $customPrompt['description'] ?? null);
        self::assertSame('subject', $customPrompt['arguments'][0]['name'] ?? null);
        self::assertTrue($customPrompt['arguments'][0]['required'] ?? false);

        $getPayload = [
            'jsonrpc' => '2.0',
            'id' => 25,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'custom_summary',
                'arguments' => [
                    'subject' => 'ElasticMS',
                ],
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($getPayload)
        );

        self::assertResponseIsSuccessful();
        $getResponse = $this->decodeResponse($this->client);
        $message = $getResponse['result']['messages'][0] ?? null;
        self::assertIsArray($message);
        self::assertSame('user', $message['role'] ?? null);
        self::assertSame('text', $message['content']['type'] ?? null);
        self::assertSame('Summarize ElasticMS with custom_summary.', $message['content']['text'] ?? null);
    }

    public function testGetDocumentUsesAuthenticatedUserPermissions(): void
    {
        $fixtures = $this->createAuthenticatedUserWithNewsContent();
        $sessionId = $this->initializeSession($this->client);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_news',
                'arguments' => [
                    'ouuid' => $fixtures['revision']->getOuuid(),
                ],
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders($sessionId),
            content: $this->jsonEncode($payload)
        );

        self::assertResponseIsSuccessful();
        $response = $this->decodeResponse($this->client);
        $structuredContent = $response['result']['structuredContent'] ?? null;

        self::assertIsArray($structuredContent);
        self::assertSame('news', $structuredContent['contentType'] ?? null);
        self::assertSame($fixtures['revision']->getOuuid(), $structuredContent['ouuid'] ?? null);
        self::assertSame('Published News', $structuredContent['rawData']['title'] ?? null);
        self::assertSame('Internal only notes', $structuredContent['rawData']['internalNotes'] ?? null);
    }

    #[\Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * @return array{revision: Revision}
     */
    private function createAuthenticatedUserWithNewsContent(): array
    {
        $user = new User();
        $user->setUsername('mcp-user');
        $user->setEmail('mcp@example.test');
        $user->setEnabled(true);
        $user->setPassword('not-used');
        $user->setRoles(['ROLE_API', 'ROLE_AUTHOR']);

        $authToken = new AuthToken($user)->setValue(self::API_TOKEN);

        $environment = new Environment();
        $environment->setName('preview');
        $environment->setLabel('Preview');
        $environment->setDescription('<p>Preview</p><p><strong>description</strong> &amp; details</p>');
        $environment->setAlias('preview_alias');
        $environment->setManaged(true);
        $environment->setOrderKey(1);

        $contentType = new ContentType()
            ->setName('news')
            ->setSingularName('News')
            ->setPluralName('News')
            ->setDescription('<p>News</p><p><strong>description</strong> &amp; details</p>')
            ->setActive(true)
            ->setOrderKey(1)
            ->setEnvironment($environment);
        $contentType->setRoles(new ContentTypeRoles([
            ContentTypeRoles::VIEW => 'ROLE_AUTHOR',
            ContentTypeRoles::CREATE => 'ROLE_AUTHOR',
        ]));
        $contentType->getFieldType()->addChild(
            new FieldType()
                ->setName('title')
                ->setType(TextStringFieldType::class)
                ->setOptions([
                    'displayOptions' => [
                        'label' => 'Title',
                    ],
                    'restrictionOptions' => [
                        'mandatory' => true,
                    ],
                ])
        );
        $contentType->getFieldType()->addChild(
            new FieldType()
                ->setName('body')
                ->setType(NestedFieldType::class)
                ->setOptions([
                    'displayOptions' => [
                        'label' => 'Body',
                    ],
                ])
                ->addChild(
                    new FieldType()
                        ->setName('summary')
                        ->setType(TextStringFieldType::class)
                        ->setOptions([
                            'displayOptions' => [
                                'label' => 'Summary',
                            ],
                        ])
                )
        );
        $contentType->getFieldType()->addChild(
            new FieldType()
                ->setName('translations')
                ->setType(MultiplexedTabContainerFieldType::class)
                ->setOptions([
                    'displayOptions' => [
                        'label' => 'Translations',
                        'values' => "nl\nfr",
                        'labels' => "Nederlands\nFrançais",
                    ],
                ])
                ->addChild(
                    new FieldType()
                        ->setName('title')
                        ->setType(TextStringFieldType::class)
                        ->setOptions([
                            'displayOptions' => [
                                'label' => 'Title',
                            ],
                            'restrictionOptions' => [
                                'mandatory' => true,
                            ],
                        ])
                )
                ->addChild(
                    new FieldType()
                        ->setName('summary')
                        ->setType(TextStringFieldType::class)
                        ->setOptions([
                            'displayOptions' => [
                                'label' => 'Summary',
                            ],
                            'restrictionOptions' => [
                                'mandatory' => true,
                            ],
                        ])
                )
                ->addChild(
                    new FieldType()
                        ->setName('body')
                        ->setType(TextStringFieldType::class)
                        ->setOptions([
                            'displayOptions' => [
                                'label' => 'Body',
                            ],
                            'restrictionOptions' => [
                                'mandatory' => true,
                            ],
                        ])
                )
        );
        $contentType->getFieldType()->addChild(
            new FieldType()
                ->setName('authors')
                ->setType(CollectionFieldType::class)
                ->setOptions([
                    'displayOptions' => [
                        'label' => 'Authors',
                    ],
                ])
                ->addChild(
                    new FieldType()
                        ->setName('name')
                        ->setType(TextStringFieldType::class)
                        ->setOptions([
                            'displayOptions' => [
                                'label' => 'Name',
                            ],
                        ])
                )
        );
        $contentType->getFieldType()->addChild(
            new FieldType()
                ->setName('status')
                ->setType(ChoiceFieldType::class)
                ->setOptions([
                    'displayOptions' => [
                        'label' => 'Status',
                        'choices' => "draft\npublished",
                    ],
                ])
        );

        $restrictedContentType = new ContentType()
            ->setName('secret')
            ->setSingularName('Secret')
            ->setPluralName('Secrets')
            ->setActive(true)
            ->setOrderKey(2)
            ->setEnvironment($environment);
        $restrictedContentType->setRoles(new ContentTypeRoles([
            ContentTypeRoles::VIEW => 'ROLE_AUTHOR',
            ContentTypeRoles::EDIT => 'ROLE_ADMIN',
            ContentTypeRoles::CREATE => 'ROLE_ADMIN',
        ]));

        $revision = new Revision()
            ->setContentType($contentType)
            ->setDeleted(false)
            ->setDraft(false)
            ->setOuuid('news-1')
            ->setEndTime(null)
            ->setRawData([
                'title' => 'Published News',
                'internalNotes' => 'Internal only notes',
            ])
            ->setLockBy('mcp-user')
            ->setLockUntil(new \DateTime('+1 hour'));

        $stylesSet = new WysiwygStylesSet()
            ->setName('bootstrap')
            ->setConfig($this->jsonEncode([
                [
                    'name' => 'Call-To-Action',
                    'element' => 'a',
                    'attributes' => [
                        'class' => 'btn btn-primary',
                    ],
                ],
                [
                    'name' => 'Attention',
                    'element' => 'div',
                    'attributes' => [
                        'class' => 'attention',
                    ],
                ],
            ]))
            ->setOrderKey(1)
            ->setTableDefaultCss('table table-bordered');

        $mcpResource = new McpResource();
        $mcpResource->setName('custom_site_info');
        $mcpResource->setLabel('Custom site info');
        $mcpResource->setUri('elasticms://custom/site-info');
        $mcpResource->setRole('ROLE_AUTHOR');
        $mcpResource->setDescription('Custom site information');
        $mcpResource->setResponse('{"resourceName":"{{ resource.name }}","label":"{{ resource.label }}"}');

        $mcpPrompt = new McpPrompt();
        $mcpPrompt->setName('custom_summary');
        $mcpPrompt->setLabel('Custom summary');
        $mcpPrompt->setRole('ROLE_AUTHOR');
        $mcpPrompt->setDescription('Build a custom summary');
        $mcpPrompt->setArguments('[{"name":"subject","description":"Subject to summarize","required":true}]');
        $mcpPrompt->setResponse('[{"role":"user","content":"Summarize {{ subject }} with {{ prompt.name }}."}]');

        $this->entityManager->persist($user);
        $this->entityManager->persist($environment);
        $this->entityManager->persist($contentType);
        $this->entityManager->persist($restrictedContentType);
        $this->entityManager->persist($revision);
        $this->entityManager->persist($authToken);
        $this->entityManager->persist($stylesSet);
        $this->entityManager->persist($mcpResource);
        $this->entityManager->persist($mcpPrompt);
        $this->entityManager->flush();

        $this->entityManager->clear();

        /** @var Revision $persistedRevision */
        $persistedRevision = $this->entityManager->getRepository(Revision::class)->find($revision->getId());

        return [
            'revision' => $persistedRevision,
        ];
    }

    private function initializeSession(KernelBrowser $client): string
    {
        $client->request(
            Request::METHOD_POST,
            '/api/mcp',
            server: $this->mcpHeaders(),
            content: $this->jsonEncode($this->initializePayload())
        );

        self::assertResponseIsSuccessful();
        $sessionId = $client->getResponse()->headers->get('Mcp-Session-Id');
        self::assertNotNull($sessionId);

        return $sessionId;
    }

    /**
     * @return array{jsonrpc: '2.0', id: int, method: 'initialize', params: array{protocolVersion: string, capabilities: array<mixed>, clientInfo: array{name: string, version: string}}}
     */
    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'phpunit',
                    'version' => '1.0.0',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mcpHeaders(?string $sessionId = null): array
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.self::API_TOKEN,
            'HTTP_HOST' => 'localhost',
        ];

        if (null !== $sessionId) {
            $headers['HTTP_MCP_SESSION_ID'] = $sessionId;
        }

        return $headers;
    }

    /**
     * @return array<mixed>
     */
    private function decodeResponse(KernelBrowser $client): array
    {
        $decoded = \json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<mixed> $payload
     */
    private function jsonEncode(array $payload): string
    {
        $encoded = \json_encode($payload, \JSON_THROW_ON_ERROR);

        return \is_string($encoded) ? $encoded : '';
    }
}
