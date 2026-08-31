<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Mcp;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\McpTool;
use EMS\CoreBundle\Repository\McpToolRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

final readonly class McpToolService implements EntityServiceInterface
{
    public function __construct(
        private McpToolRepository $mcpToolRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @return McpTool[]
     */
    public function getAll(): array
    {
        return $this->mcpToolRepository->getAll();
    }

    /**
     * @return McpTool[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->mcpToolRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'mcp-tool';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'mcp-tools',
            'McpTool',
            'McpTools',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->mcpToolRepository->counter($searchValue);
    }

    public function update(McpTool $mcpTool): void
    {
        $mcpTool->setName(new Encoder()->slug(text: $mcpTool->getName(), separator: '_')->toString());
        $this->mcpToolRepository->create($mcpTool);
    }

    public function delete(McpTool $mcpTool): void
    {
        $name = $mcpTool->getName();
        $this->mcpToolRepository->delete($mcpTool);
        $this->logger->warning('log.service.mcp_tool.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->mcpToolRepository->getByIds($ids) as $mcpTool) {
            $this->delete($mcpTool);
        }
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->mcpToolRepository->getByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof McpTool) {
            throw new \RuntimeException('Unexpected mcp tool object');
        }

        $mcpTool = McpTool::fromJson($json, $entity);
        $this->update($mcpTool);

        return $mcpTool;
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $mcpTool = McpTool::fromJson($json);
        if (null !== $name && $mcpTool->getName() !== $name) {
            throw new \RuntimeException(\sprintf('MCP tool name mismatched: %s vs %s', $mcpTool->getName(), $name));
        }
        $this->update($mcpTool);

        return $mcpTool;
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $mcpTool = $this->mcpToolRepository->getByName($name);
        if (null === $mcpTool) {
            throw new \RuntimeException(\sprintf('MCP tool %s not found', $name));
        }

        $id = $mcpTool->getId();
        $this->delete($mcpTool);

        return $id;
    }
}
