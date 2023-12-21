<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Log;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Entity\Log;
use EMS\CoreBundle\Repository\LogRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

class LogManager implements EntityServiceInterface
{
    public function __construct(private readonly LogRepository $logRepository, private readonly LoggerInterface $logger)
    {
    }

    public function delete(Log $log): void
    {
        $created = $log->getCreated();
        $this->logRepository->delete($log);
        $this->logger->warning('log.service.log.delete', [
            'created' => $created->format('c'),
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        $count = 0;
        foreach ($this->logRepository->getByIds($ids) as $log) {
            $this->logRepository->delete($log);
            ++$count;
        }
        $this->logger->warning('log.service.log.deletes', [
            'count' => $count,
        ]);
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (!$context instanceof LogEntityTableContext) {
            throw new \RuntimeException('Unexpected context');
        }

        $context->from = $from;
        $context->size = $size;
        $context->orderField = $orderField;
        $context->orderDirection = $orderDirection;
        $context->searchValue = $searchValue;

        return $this->logRepository->get($context);
    }

    public function getEntityName(): string
    {
        return 'log';
    }

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (!$context instanceof LogEntityTableContext) {
            throw new \RuntimeException('Unexpected context');
        }

        $context->searchValue = $searchValue;

        return $this->logRepository->counter($context);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        throw new \RuntimeException('getByItemName method not yet implemented');
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}
