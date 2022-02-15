<?php

namespace EMS\CoreBundle\Core\Mapping;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Repository\FilterRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

class FilterManager implements EntityServiceInterface
{
    private FilterRepository $filterRepository;

    public function __construct(FilterRepository $filterRepository)
    {
        $this->filterRepository = $filterRepository;
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        return $this->filterRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'filter';
    }

    public function getAliasesName(): array
    {
        return [
            'filters',
            'Filter',
            'Filters',
        ];
    }

    public function count(string $searchValue = '', $context = null): int
    {
        return $this->filterRepository->counter($searchValue);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->filterRepository->findByName($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        $filter = Filter::fromJson($json, $entity);
        $this->filterRepository->update($filter);

        return $filter;
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $filter = Filter::fromJson($json);
        if (null !== $name && $filter->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Filter name mismatched: %s vs %s', $filter->getName(), $name));
        }
        $this->filterRepository->update($filter);

        return $filter;
    }

    public function deleteByItemName(string $name): string
    {
        $filter = $this->filterRepository->findByName($name);
        if (null === $filter) {
            throw new \RuntimeException(\sprintf('Filter %s not found', $name));
        }
        $id = $filter->getId();
        $this->filterRepository->delete($filter);

        return \strval($id);
    }
}
