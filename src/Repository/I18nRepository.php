<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\I18n;

/**
 * @extends ServiceEntityRepository<I18n>
 *
 * @method I18n|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
class I18nRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, I18n::class);
    }

    public function delete(I18n $i18n): void
    {
        $this->getEntityManager()->remove($i18n);
        $this->getEntityManager()->flush();
    }

    public function findByIdentifier(string $id): ?I18n
    {
        return $this->findOneBy(['identifier' => $id]);
    }

    /**
     * @return I18n[]
     */
    public function getByIds(string ...$ids): array
    {
        $qb = $this->createQueryBuilder('i');
        $qb
            ->andWhere($qb->expr()->in('i.id', ':ids'))
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        return $qb->getQuery()->getResult();
    }

    public function makeQueryBuilder(string $searchValue = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i');

        if ('' !== $searchValue) {
            $qb
                ->andWhere($qb->expr()->like('i.identifier', ':term'))
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }

        return $qb;
    }

    public function update(I18n $i18n): void
    {
        $this->getEntityManager()->persist($i18n);
        $this->getEntityManager()->flush();
    }
}
