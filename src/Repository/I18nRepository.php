<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\I18n;

/**
 * @extends ServiceEntityRepository<I18n>
 *
 * @method I18n|null findOneBy(array $criteria, array $orderBy = null)
 */
class I18nRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, I18n::class);
    }

    public function countWithFilter(?string $identifier): int
    {
        $qb = $this->createQueryBuilder('i')
        ->select('COUNT(i)');

        if (null != $identifier) {
            $qb->where('i.identifier LIKE :identifier')
            ->setParameter('identifier', '%'.$identifier.'%');
        }

        return $qb->getQuery()
        ->getSingleScalarResult();
    }

    /**
     * @return iterable|I18n[]
     */
    public function findByWithFilter(int $limit, int $from, ?string $identifier): iterable
    {
        $qb = $this->createQueryBuilder('i')
        ->select('i');

        if (null != $identifier) {
            $qb->where('i.identifier LIKE :identifier')
            ->setParameter('identifier', '%'.$identifier.'%');
        }

        $qb->orderBy('i.identifier', 'ASC')
        ->setFirstResult($from)
        ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function update(I18n $styleSet): void
    {
        $this->getEntityManager()->persist($styleSet);
        $this->getEntityManager()->flush();
    }

    public function delete(I18n $styleSet): void
    {
        $this->getEntityManager()->remove($styleSet);
        $this->getEntityManager()->flush();
    }

    public function findByIdentifier(string $id): ?I18n
    {
        return $this->findOneBy(['identifier' => $id]);
    }

    /**
     * @return I18n[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('i18n')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);
        $qb->orderBy('i18n.identifier', $orderDirection);

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('i18n.identifier', ':term'),
                $qb->expr()->like('i18n.content', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('i18n');
        $qb->select('count(i18n.id)');
        $this->addSearchFilters($qb, $searchValue);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }
}
