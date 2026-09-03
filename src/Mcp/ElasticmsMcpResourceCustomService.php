<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Entity\McpResource;
use EMS\CoreBundle\Service\Mcp\McpResourceService;
use EMS\CoreBundle\Service\UserService;
use Mcp\Exception\ResourceReadException;
use Mcp\Server\Builder;
use Psr\Log\LoggerInterface;
use Twig\Environment;

final readonly class ElasticmsMcpResourceCustomService
{
    public function __construct(
        private UserService $userService,
        private McpResourceService $mcpResourceService,
        private Environment $twig,
        private LoggerInterface $logger,
        private LoggerInterface $auditLogger,
    ) {
    }

    public function addCustomResources(Builder $builder): void
    {
        foreach ($this->mcpResourceService->getAll() as $mcpResource) {
            if (!$this->isGranted($mcpResource)) {
                continue;
            }

            $builder->addResource(
                handler: fn (): string => $this->readCustomResource($mcpResource),
                uri: $mcpResource->getUri(),
                name: $mcpResource->getName(),
                title: $mcpResource->getLabel(),
                description: $mcpResource->getDescription(),
                mimeType: $mcpResource->getMimeType(),
            );
        }
    }

    private function readCustomResource(McpResource $mcpResource): string
    {
        $template = $mcpResource->getResponse();
        if (null === $template || '' === \trim($template)) {
            throw new ResourceReadException(\sprintf('MCP resource "%s" has no Response Twig template configured.', $mcpResource->getName()));
        }

        try {
            $this->auditLogger->notice('log.mcp.resource.read', [
                'mcp_resource' => $mcpResource->getName(),
            ]);

            return $this->twig->createTemplate($template)->render([
                'resource' => $mcpResource,
            ]);
        } catch (ResourceReadException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('MCP resource "%s" read failed: %s', $mcpResource->getName(), $e->getMessage()), [
                'exception' => $e,
            ]);

            throw new ResourceReadException(\sprintf('MCP resource "%s" read failed.', $mcpResource->getName()), 0, $e);
        }
    }

    private function isGranted(McpResource $mcpResource): bool
    {
        if (!$mcpResource->isEnabled()) {
            return false;
        }

        return $this->userService->isGrantedRole($mcpResource->getRole());
    }
}
