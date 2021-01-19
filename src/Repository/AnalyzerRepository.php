<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\Analyzer;

class AnalyzerRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Analyzer::class);
    }

    public function findByName($name): ?Analyzer
    {
        $analyzer = $this->findOneBy([
            'name' => $name,
        ]);
        if (null !== $analyzer && !$analyzer instanceof Analyzer) {
            throw new \RuntimeException('Unexpected analyzer type');
        }

        return $analyzer;
    }

    public function update(Analyzer $analyzer): void
    {
        $this->getEntityManager()->persist($analyzer);
        $this->getEntityManager()->flush();
    }
}
