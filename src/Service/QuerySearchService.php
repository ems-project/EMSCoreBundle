<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Repository\QuerySearchRepository;
use Psr\Log\LoggerInterface;

final class QuerySearchService implements EntityServiceInterface
{
    private QuerySearchRepository $querySearchRepository;
    private LoggerInterface $logger;

    public function __construct(QuerySearchRepository $querySearchRepository, LoggerInterface $logger)
    {
        $this->querySearchRepository = $querySearchRepository;
        $this->logger = $logger;
    }

    /**
     * @return QuerySearch[]
     */
    public function getAll(): array
    {
        return $this->querySearchRepository->getAll();
    }

    public function update(QuerySearch $querySearch): void
    {
        if (0 === $querySearch->getOrderKey()) {
            $querySearch->setOrderKey($this->querySearchRepository->counter() + 1);
        }
        $encoder = new Encoder();
        $name = $querySearch->getName();
        if (null === $name) {
            throw new \RuntimeException('Unexpected null name');
        }
        $webalized = $encoder->webalize($name);
        if (null === $webalized) {
            throw new \RuntimeException('Unexpected null webalized name');
        }
        $querySearch->setName($webalized);
        $this->querySearchRepository->create($querySearch);
    }

    public function delete(QuerySearch $querySearch): void
    {
        $name = $querySearch->getName();
        $this->querySearchRepository->delete($querySearch);
        $this->logger->warning('log.service.query_search.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->querySearchRepository->getByIds($ids) as $querySearch) {
            $this->delete($querySearch);
        }
    }

    /**
     * @param array<string, int> $ids
     */
    public function reorderByIds(array $ids): void
    {
        foreach ($this->querySearchRepository->getByIds(\array_keys($ids)) as $querySearch) {
            $querySearch->setOrderKey(isset($ids[$querySearch->getId()]) ? $ids[$querySearch->getId()] + 1 : 0);
            $this->querySearchRepository->create($querySearch);
        }
    }

    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @param string[] $ids
     *
     * @return QuerySearch[]
     */
    public function getByIds(array $ids): array
    {
        return $this->querySearchRepository->getByIds($ids);
    }

    /**
     * @param mixed $context
     *
     * @return QuerySearch[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->querySearchRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'query_search';
    }

    /**
     * @param mixed $context
     */
    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->querySearchRepository->counter();
    }
}
