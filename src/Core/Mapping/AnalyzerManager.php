<?php

namespace EMS\CoreBundle\Core\Mapping;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

class AnalyzerManager implements EntityServiceInterface
{
    private AnalyzerRepository $AnalyzerRepository;

    public function __construct(AnalyzerRepository $AnalyzerRepository)
    {
        $this->AnalyzerRepository = $AnalyzerRepository;
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        return $this->AnalyzerRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'analyzer';
    }

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

    public function count(string $searchValue = '', $context = null): int
    {
        return $this->AnalyzerRepository->counter($searchValue);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->AnalyzerRepository->findByName($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        $Analyzer = Analyzer::fromJson($json, $entity);
        $this->AnalyzerRepository->update($Analyzer);

        return $Analyzer;
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $Analyzer = Analyzer::fromJson($json);
        if (null !== $name && $Analyzer->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Analyzer name mismatched: %s vs %s', $Analyzer->getName(), $name));
        }
        $this->AnalyzerRepository->update($Analyzer);

        return $Analyzer;
    }

    public function deleteByItemName(string $name): string
    {
        $Analyzer = $this->AnalyzerRepository->findByName($name);
        if (null === $Analyzer) {
            throw new \RuntimeException(\sprintf('Analyzer %s not found', $name));
        }
        $id = $Analyzer->getId();
        $this->AnalyzerRepository->delete($Analyzer);

        return \strval($id);
    }
}
