<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Mcp;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\McpPrompt;
use EMS\CoreBundle\Repository\McpPromptRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

final readonly class McpPromptService implements EntityServiceInterface
{
    public function __construct(
        private McpPromptRepository $mcpPromptRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @return McpPrompt[]
     */
    public function getAll(): array
    {
        return $this->mcpPromptRepository->getAll();
    }

    /**
     * @return McpPrompt[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->mcpPromptRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'mcp-prompt';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'mcp-prompts',
            'McpPrompt',
            'McpPrompts',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->mcpPromptRepository->counter($searchValue);
    }

    public function update(McpPrompt $mcpPrompt): void
    {
        $mcpPrompt->setName(new Encoder()->slug(text: $mcpPrompt->getName(), separator: '_')->toString());
        $this->mcpPromptRepository->create($mcpPrompt);
    }

    public function delete(McpPrompt $mcpPrompt): void
    {
        $name = $mcpPrompt->getName();
        $this->mcpPromptRepository->delete($mcpPrompt);
        $this->logger->warning('log.service.mcp_prompt.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->mcpPromptRepository->getByIds($ids) as $mcpPrompt) {
            $this->delete($mcpPrompt);
        }
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->mcpPromptRepository->getByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof McpPrompt) {
            throw new \RuntimeException('Unexpected mcp prompt object');
        }

        $mcpPrompt = McpPrompt::fromJson($json, $entity);
        $this->update($mcpPrompt);

        return $mcpPrompt;
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $mcpPrompt = McpPrompt::fromJson($json);
        if (null !== $name && $mcpPrompt->getName() !== $name) {
            throw new \RuntimeException(\sprintf('MCP prompt name mismatched: %s vs %s', $mcpPrompt->getName(), $name));
        }
        $this->update($mcpPrompt);

        return $mcpPrompt;
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $mcpPrompt = $this->mcpPromptRepository->getByName($name);
        if (null === $mcpPrompt) {
            throw new \RuntimeException(\sprintf('MCP prompt %s not found', $name));
        }

        $id = $mcpPrompt->getId();
        $this->delete($mcpPrompt);

        return $id;
    }
}
