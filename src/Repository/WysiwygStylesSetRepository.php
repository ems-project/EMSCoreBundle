<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\WysiwygStylesSet;

/**
 * @extends ServiceEntityRepository<WysiwygStylesSet>
 *
 * @method WysiwygStylesSet|null find($id)
 * @method WysiwygStylesSet|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
class WysiwygStylesSetRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, WysiwygStylesSet::class);
    }

    public function delete(WysiwygStylesSet $styleSet): void
    {
        $this->getEntityManager()->remove($styleSet);
        $this->getEntityManager()->flush();
    }

    /**
     * @return WysiwygStylesSet[]
     */
    #[\Override]
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }

    public function findById(int $id): ?WysiwygStylesSet
    {
        return $this->find($id);
    }

    public function getById(string $id): WysiwygStylesSet
    {
        if (null === $wysiwygStylesSet = $this->find($id)) {
            throw new \RuntimeException('Unexpected WysiwygStylesSet type');
        }

        return $wysiwygStylesSet;
    }

    /**
     * @return WysiwygStylesSet[]
     */
    public function getByIds(string ...$ids): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb
            ->andWhere($qb->expr()->in('s.id', ':ids'))
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        return $qb->getQuery()->getResult();
    }

    public function getByName(string $name): ?WysiwygStylesSet
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function makeQueryBuilder(string $searchValue = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');

        if ('' !== $searchValue) {
            $qb
                ->andWhere($qb->expr()->like('s.name', ':term'))
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }

        return $qb;
    }

    public function update(WysiwygStylesSet $styleSet): void
    {
        $this->getEntityManager()->persist($styleSet);
        $this->getEntityManager()->flush();
    }
}
