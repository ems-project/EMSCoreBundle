<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mapping;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Repository\FilterRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

class FilterManager implements EntityServiceInterface
{
    public function __construct(private readonly FilterRepository $filterRepository)
    {
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->filterRepository->makeQueryBuilder(searchValue: $searchValue)
            ->select('count(f.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $filter = Filter::fromJson($json);
        if (null !== $name && $filter->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Filter name mismatched: %s vs %s', $filter->getName(), $name));
        }
        $this->filterRepository->update($filter);

        return $filter;
    }

    public function delete(Filter $filter): void
    {
        $this->filterRepository->delete($filter);
    }

    public function deleteByIds(string ...$ids): void
    {
        $filters = $this->filterRepository->getByIds(...$ids);
        foreach ($filters as $filter) {
            $this->delete($filter);
        }
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $filter = $this->filterRepository->findByName($name);
        if (null === $filter) {
            throw new \RuntimeException(\sprintf('Filter %s not found', $name));
        }
        $id = $filter->getId();
        $this->filterRepository->delete($filter);

        return (string) $id;
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->filterRepository->makeQueryBuilder(searchValue: $searchValue);
        $qb->setFirstResult($from)->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('f.%s', $orderField), $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'filters',
            'Filter',
            'Filters',
        ];
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->filterRepository->findByName($name);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'filter';
    }

    #[\Override]
    public function isSortable(): bool
    {
        return true;
    }

    public function reorderByIds(string ...$ids): void
    {
        $counter = 1;

        foreach ($ids as $id) {
            $filter = $this->filterRepository->getById($id);
            $filter->setOrderKey($counter++);
            $this->filterRepository->update($filter);
        }
    }

    public function update(Filter $filter): void
    {
        if (0 === $filter->getOrderKey()) {
            $filter->setOrderKey($this->count() + 1);
        }

        $this->filterRepository->update($filter);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        $filter = Filter::fromJson($json, $entity);
        $this->filterRepository->update($filter);

        return $filter;
    }
}
