<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Entity\McpPrompt;
use EMS\CoreBundle\Service\Mcp\McpPromptService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Json;
use Mcp\Exception\PromptGetException;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Server\Builder;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\PromptHandlerInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;

final readonly class ElasticmsMcpPromptCustomService
{
    public function __construct(
        private UserService $userService,
        private McpPromptService $mcpPromptService,
        private Environment $twig,
        private LoggerInterface $logger,
        private LoggerInterface $auditLogger,
    ) {
    }

    public function addCustomPrompts(Builder $builder): void
    {
        foreach ($this->mcpPromptService->getAll() as $mcpPrompt) {
            if (!$this->isGranted($mcpPrompt)) {
                continue;
            }

            $builder->add(
                definition: new Prompt(
                    name: $mcpPrompt->getName(),
                    title: $mcpPrompt->getLabel(),
                    description: $mcpPrompt->getDescription(),
                    arguments: $this->buildArguments($mcpPrompt),
                ),
                handler: new readonly class($this, $mcpPrompt) implements PromptHandlerInterface {
                    public function __construct(
                        private ElasticmsMcpPromptCustomService $service,
                        private McpPrompt $mcpPrompt,
                    ) {
                    }

                    /**
                     * @param array<string, mixed> $arguments
                     */
                    #[\Override]
                    public function get(array $arguments, ClientGateway $gateway): mixed
                    {
                        return $this->service->getCustomPrompt($this->mcpPrompt, $arguments);
                    }
                },
            );
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function getCustomPrompt(McpPrompt $mcpPrompt, array $arguments): mixed
    {
        $template = $mcpPrompt->getResponse();
        if (null === $template || '' === \trim($template)) {
            throw new PromptGetException(\sprintf('MCP prompt "%s" has no Response Twig template configured.', $mcpPrompt->getName()));
        }

        try {
            $this->auditLogger->notice('log.mcp.prompt.get', [
                'mcp_prompt' => $mcpPrompt->getName(),
            ]);

            $rendered = $this->twig->createTemplate($template)->render([
                'prompt' => $mcpPrompt,
                'arguments' => $arguments,
                ...$arguments,
            ]);

            return Json::decode($rendered);
        } catch (PromptGetException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('MCP prompt "%s" get failed: %s', $mcpPrompt->getName(), $e->getMessage()), [
                'exception' => $e,
            ]);

            throw new PromptGetException(\sprintf('MCP prompt "%s" get failed.', $mcpPrompt->getName()), 0, $e);
        }
    }

    private function isGranted(McpPrompt $mcpPrompt): bool
    {
        if (!$mcpPrompt->isEnabled()) {
            return false;
        }

        return $this->userService->isGrantedRole($mcpPrompt->getRole());
    }

    /**
     * @return PromptArgument[]|null
     */
    private function buildArguments(McpPrompt $mcpPrompt): ?array
    {
        $template = $mcpPrompt->getArguments();
        if (null === $template || '' === \trim($template)) {
            return null;
        }

        $rendered = $this->twig->createTemplate($template)->render([
            'prompt' => $mcpPrompt,
        ]);
        $arguments = Json::decode($rendered);
        if (!\array_is_list($arguments)) {
            throw new PromptGetException(\sprintf('MCP prompt "%s" arguments template must render a JSON array.', $mcpPrompt->getName()));
        }

        return \array_map(static function (mixed $argument) use ($mcpPrompt): PromptArgument {
            if (!\is_array($argument) || !isset($argument['name']) || !\is_string($argument['name'])) {
                throw new PromptGetException(\sprintf('MCP prompt "%s" arguments template must render an array of objects.', $mcpPrompt->getName()));
            }
            $description = $argument['description'] ?? null;
            if (null !== $description && !\is_string($description)) {
                throw new PromptGetException(\sprintf('MCP prompt "%s" argument descriptions must be strings.', $mcpPrompt->getName()));
            }
            $required = $argument['required'] ?? null;
            if (null !== $required && !\is_bool($required)) {
                throw new PromptGetException(\sprintf('MCP prompt "%s" argument required flags must be booleans.', $mcpPrompt->getName()));
            }

            return new PromptArgument(
                name: $argument['name'],
                description: $description,
                required: $required,
            );
        }, $arguments);
    }
}
