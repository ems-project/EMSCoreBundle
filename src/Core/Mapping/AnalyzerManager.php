<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mapping;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

class AnalyzerManager implements EntityServiceInterface
{
    public function __construct(private readonly AnalyzerRepository $analyzerRepository)
    {
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->analyzerRepository->makeQueryBuilder(searchValue: $searchValue)
            ->select('count(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $analyzer = Analyzer::fromJson($json);
        if (null !== $name && $analyzer->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Analyzer name mismatched: %s vs %s', $analyzer->getName(), $name));
        }
        $this->analyzerRepository->update($analyzer);

        return $analyzer;
    }

    public function delete(Analyzer $analyzer): void
    {
        $this->analyzerRepository->delete($analyzer);
    }

    public function deleteByIds(string ...$ids): void
    {
        $analyzers = $this->analyzerRepository->getByIds(...$ids);
        foreach ($analyzers as $analyzer) {
            $this->delete($analyzer);
        }
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $analyzer = $this->analyzerRepository->findByName($name);
        if (null === $analyzer) {
            throw new \RuntimeException(\sprintf('Analyzer %s not found', $name));
        }
        $id = $analyzer->getId();
        $this->analyzerRepository->delete($analyzer);

        return (string) $id;
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->analyzerRepository->makeQueryBuilder(searchValue: $searchValue);
        $qb->setFirstResult($from)->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('a.%s', $orderField), $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'analyzers',
            'Analyzer',
            'Analyzers',
            'analyser',
            'analysers',
            'Analyser',
            'Analysers',
        ];
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->analyzerRepository->findByName($name);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'analyzer';
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
            $analyzer = $this->analyzerRepository->getById($id);
            $analyzer->setOrderKey($counter++);
            $this->analyzerRepository->update($analyzer);
        }
    }

    public function update(Analyzer $analyzer): void
    {
        if (0 === $analyzer->getOrderKey()) {
            $analyzer->setOrderKey($this->count() + 1);
        }

        $this->analyzerRepository->update($analyzer);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        $analyzer = Analyzer::fromJson($json, $entity);
        $this->analyzerRepository->update($analyzer);

        return $analyzer;
    }
}
