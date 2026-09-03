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
        private ElasticmsMcpToolCustomService $toolCustomService,
        private ElasticmsMcpPromptCustomService $promptCustomService,
        private ElasticmsMcpResourceCustomService $customResourceService,
        private ElasticmsMcpResourceEnvironmentService $environmentResourceService,
        private ElasticmsMcpResourceContentTypeService $contentTypeResourceService,
        private ElasticmsMcpResourceWysiwygStyleService $wysiwygStyleResourceService,
    ) {
    }

    public function create(): Server
    {
        $builder = Server::builder()
            ->setServerInfo(
                name: 'elasticMS MCP',
                version: '1.0.0',
                description: 'elasticMS MCP server over HTTP using elasticMS API bearer tokens.',
            )
            ->setInstructions('Authenticate with an elasticMS API bearer token. The server exposes content, search, user and asset tools while preserving the authenticated user permissions.')
            ->setContainer($this->container)
            ->setLogger($this->logger)
            ->setSession(new FileSessionStore($this->cacheDir.'/mcp-sessions'));
        $this->toolCustomService->addCustomTools($builder);
        $this->toolUserService->addUserTools($builder);
        $this->toolAssetService->addAssetTools($builder);
        $this->toolDataService->addDataTools($builder);
        $this->toolDataService->addGetDocumentTools($builder);
        $this->toolDataService->addSaveDocumentTools($builder);

        $this->promptCustomService->addCustomPrompts($builder);

        $this->customResourceService->addCustomResources($builder);
        $this->environmentResourceService->addEnvironmentResources($builder);
        $this->contentTypeResourceService->addContentTypeResources($builder);
        $this->wysiwygStyleResourceService->addWysiwygStyleResources($builder);

        $this->toolCustomService->addCustomTools($builder);

        return $builder->build();
    }
}
