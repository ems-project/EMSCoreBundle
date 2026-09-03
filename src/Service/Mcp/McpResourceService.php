<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Mcp;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\McpResource;
use EMS\CoreBundle\Repository\McpResourceRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

final readonly class McpResourceService implements EntityServiceInterface
{
    public function __construct(
        private McpResourceRepository $mcpResourceRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @return McpResource[]
     */
    public function getAll(): array
    {
        return $this->mcpResourceRepository->getAll();
    }

    /**
     * @return McpResource[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->mcpResourceRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'mcp-resource';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'mcp-resources',
            'McpResource',
            'McpResources',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->mcpResourceRepository->counter($searchValue);
    }

    public function update(McpResource $mcpResource): void
    {
        $mcpResource->setName(new Encoder()->slug(text: $mcpResource->getName(), separator: '_')->toString());
        $this->mcpResourceRepository->create($mcpResource);
    }

    public function delete(McpResource $mcpResource): void
    {
        $name = $mcpResource->getName();
        $this->mcpResourceRepository->delete($mcpResource);
        $this->logger->warning('log.service.mcp_resource.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->mcpResourceRepository->getByIds($ids) as $mcpResource) {
            $this->delete($mcpResource);
        }
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->mcpResourceRepository->getByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof McpResource) {
            throw new \RuntimeException('Unexpected mcp resource object');
        }

        $mcpResource = McpResource::fromJson($json, $entity);
        $this->update($mcpResource);

        return $mcpResource;
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $mcpResource = McpResource::fromJson($json);
        if (null !== $name && $mcpResource->getName() !== $name) {
            throw new \RuntimeException(\sprintf('MCP resource name mismatched: %s vs %s', $mcpResource->getName(), $name));
        }
        $this->update($mcpResource);

        return $mcpResource;
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $mcpResource = $this->mcpResourceRepository->getByName($name);
        if (null === $mcpResource) {
            throw new \RuntimeException(\sprintf('MCP resource %s not found', $name));
        }

        $id = $mcpResource->getId();
        $this->delete($mcpResource);

        return $id;
    }
}
