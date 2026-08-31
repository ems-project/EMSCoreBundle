<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use Mcp\Exception\ToolCallException;

trait ElasticmsMcpToolCallTrait
{
    /**
     * @template TResult
     *
     * @param array<string, mixed> $context
     * @param \Closure(): TResult  $callable
     *
     * @return TResult
     */
    protected function wrapToolCall(string $toolName, array $context, \Closure $callable): mixed
    {
        $logContext = [
            'tool' => $toolName,
            'username' => $this->userService->getCurrentUser()->getUsername(),
            ...$context,
        ];

        $this->logger->info('mcp.tool.called', $logContext);
        $this->auditLogger->info('mcp.tool.called', $logContext);

        try {
            $result = $callable();

            $this->logger->info('mcp.tool.succeeded', $logContext);
            $this->auditLogger->info('mcp.tool.succeeded', $logContext);

            return $result;
        } catch (\Throwable $throwable) {
            $errorContext = [...$logContext, 'error_message' => $throwable->getMessage()];

            $this->logger->error('mcp.tool.failed', [...$errorContext, 'exception' => $throwable]);
            $this->auditLogger->error('mcp.tool.failed', $errorContext);

            if ($throwable instanceof ToolCallException) {
                throw $throwable;
            }

            throw new ToolCallException($throwable->getMessage(), 0, $throwable);
        }
    }
}
