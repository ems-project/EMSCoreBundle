<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Entity\McpTool;
use EMS\CoreBundle\Service\Mcp\McpToolService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Json;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Builder;
use Mcp\Server\RequestContext;
use Psr\Log\LoggerInterface;
use Twig\Environment;

final readonly class ElasticmsMcpToolCustomService
{
    use ElasticmsMcpToolCallTrait;

    public function __construct(
        private UserService $userService,
        private McpToolService $mcpToolService,
        private Environment $twig,
        private LoggerInterface $logger,
        private LoggerInterface $auditLogger,
    ) {
    }

    public function addCustomTools(Builder $builder): void
    {
        foreach ($this->mcpToolService->getAll() as $mcpTool) {
            if (!$this->isGranted($mcpTool)) {
                continue;
            }

            $builder->addTool(
                handler: fn (RequestContext $context): mixed => $this->callCustomTool($mcpTool, $this->getToolArguments($context)),
                name: $mcpTool->getName(),
                description: $mcpTool->getDescription() ?? $mcpTool->getLabel(),
                inputSchema: $this->buildInputSchema($mcpTool),
                outputSchema: $this->buildOutputSchema($mcpTool),
            );
        }
    }

    /**
     * @param mixed[] $arguments
     */
    private function callCustomTool(McpTool $mcpTool, array $arguments): mixed
    {
        return $this->wrapToolCall($mcpTool->getName(), $arguments, function () use ($mcpTool, $arguments): mixed {
            $template = $mcpTool->getResponse();
            if (null === $template || '' === \trim($template)) {
                throw new ToolCallException(\sprintf('MCP tool "%s" has no Response Twig template configured.', $mcpTool->getName()));
            }
            $rendered = $this->twig->createTemplate($template)->render([
                'tool' => $mcpTool,
                ...$arguments,
            ]);

            return Json::decode($rendered);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function getToolArguments(RequestContext $context): array
    {
        $request = $context->getRequest();
        if (!$request instanceof CallToolRequest) {
            throw new ToolCallException('Unexpected MCP request type for custom tool call.');
        }

        return $request->arguments;
    }

    private function isGranted(McpTool $mcpTool): bool
    {
        if (!$mcpTool->isEnabled()) {
            return false;
        }

        return $this->userService->isGrantedRole($mcpTool->getRole());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInputSchema(McpTool $mcpTool): array
    {
        $template = $mcpTool->getInputSchema();
        if (null === $template || '' === \trim($template)) {
            throw new ToolCallException(\sprintf('MCP tool "%s" has no Input Schema Twig template configured.', $mcpTool->getName()));
        }
        $rendered = $this->twig->createTemplate($template)->render([
            'tool' => $mcpTool,
        ]);

        return ElasticmsMcpJsonSchema::normalize(Json::decode($rendered));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOutputSchema(McpTool $mcpTool): array
    {
        $template = $mcpTool->getOutputSchema();
        if (null === $template || '' === \trim($template)) {
            throw new ToolCallException(\sprintf('MCP tool "%s" has no Output Schema Twig template configured.', $mcpTool->getName()));
        }
        $rendered = $this->twig->createTemplate($template)->render([
            'tool' => $mcpTool,
        ]);

        return ElasticmsMcpJsonSchema::normalize(Json::decode($rendered));
    }
}
