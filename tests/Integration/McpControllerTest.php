<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\User;
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
        $this->entityManager = $container->get('doctrine')->getManager();

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
        $user->setUsernameCanonical('mcp-user');
        $user->setEmail('mcp@example.test');
        $user->setEmailCanonical('mcp@example.test');
        $user->setEnabled(true);
        $user->setPassword('not-used');
        $user->setRoles(['ROLE_API', 'ROLE_AUTHOR']);

        $authToken = new AuthToken($user)->setValue(self::API_TOKEN);

        $environment = new Environment();
        $environment->setName('preview');
        $environment->setAlias('preview_alias');
        $environment->setManaged(true);
        $environment->setOrderKey(1);

        $contentType = new ContentType()
            ->setName('news')
            ->setSingularName('News')
            ->setPluralName('News')
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

        $this->entityManager->persist($user);
        $this->entityManager->persist($environment);
        $this->entityManager->persist($contentType);
        $this->entityManager->persist($restrictedContentType);
        $this->entityManager->persist($revision);
        $this->entityManager->persist($authToken);
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
