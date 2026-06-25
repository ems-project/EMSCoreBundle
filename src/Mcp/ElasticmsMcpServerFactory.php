<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final readonly class ElasticmsMcpServerFactory
{
    public function __construct(
        private ContainerInterface $container,
        private string $cacheDir,
        private LoggerInterface $logger,
        private ElasticmsMcpToolUserService $toolUserService,
        private ElasticmsMcpToolDataService $toolDataService,
        private ElasticmsMcpToolAssetService $toolAssetService,
    ) {
    }

    public function create(): Server
    {
        $builder = Server::builder()
            ->setServerInfo(
                name: 'elasticMS MCP',
                version: '1.0.0',
                description: 'Minimal elasticMS MCP server over HTTP using elasticMS API bearer tokens.',
            )
            ->setInstructions('Authenticate with an elasticMS API bearer token. The server exposes a minimal set of content tools and preserves the authenticated user permissions.')
            ->setContainer($this->container)
            ->setLogger($this->logger)
            ->setSession(new FileSessionStore($this->cacheDir.'/mcp-sessions'))
            ->addTool(
                handler: $this->toolUserService->getCurrentUser(...),
                name: 'get_current_user',
                description: 'Return the authenticated elasticMS user profile.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
                    'additionalProperties' => false,
                ],
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'user' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => ['integer', 'null']],
                                'username' => ['type' => 'string'],
                                'displayName' => ['type' => 'string'],
                                'roles' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'email' => ['type' => 'string'],
                                'circles' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                                'lastLogin' => ['type' => ['string', 'null']],
                                'expirationDate' => ['type' => ['string', 'null']],
                                'language' => ['type' => 'string'],
                                'locale' => ['type' => 'string'],
                                'localePreferred' => ['type' => ['string', 'null']],
                                'userOptions' => [
                                    'type' => 'object',
                                    'additionalProperties' => true,
                                ],
                            ],
                            'required' => ['id', 'username', 'displayName', 'roles', 'email', 'circles', 'lastLogin', 'expirationDate', 'language', 'locale', 'localePreferred', 'userOptions'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'required' => ['user'],
                    'additionalProperties' => false,
                ],
            );
        $this->toolAssetService->addAssetTools($builder);
        // $this->toolDataService->addSearchTool($builder);
        $this->toolDataService->addGetDocumentTools($builder);
        $this->toolDataService->addCreateDocumentTools($builder);

        return $builder->build();
    }
}
