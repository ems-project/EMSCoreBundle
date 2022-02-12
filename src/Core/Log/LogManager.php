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
    private LogRepository $logRepository;
    private LoggerInterface $logger;

    public function __construct(LogRepository $logRepository, LoggerInterface $logger)
    {
        $this->logRepository = $logRepository;
        $this->logger = $logger;
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
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->logRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'log';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->logRepository->counter($searchValue);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        throw new \RuntimeException('getByItemName method not yet implemented');
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $name, string $json): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }
}
