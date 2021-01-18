<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\Filter;

class FilterRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Filter::class);
    }

    public function findByName($name): ?Filter
    {
        $filter = $this->findOneBy([
            'name' => $name,
        ]);
        if (null !== $filter && !$filter instanceof Filter) {
            throw new \RuntimeException('Unexpected filter type');
        }

        return $filter;
    }

    public function update(Filter $filter): void
    {
        $this->getEntityManager()->persist($filter);
        $this->getEntityManager()->flush();
    }
}
