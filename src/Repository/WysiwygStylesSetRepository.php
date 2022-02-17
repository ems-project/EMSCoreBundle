<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\WysiwygStylesSet;

class WysiwygStylesSetRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, WysiwygStylesSet::class);
    }

    /**
     * @return WysiwygStylesSet[]
     */
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }

    public function update(WysiwygStylesSet $styleSet): void
    {
        $this->getEntityManager()->persist($styleSet);
        $this->getEntityManager()->flush();
    }

    public function delete(WysiwygStylesSet $styleSet): void
    {
        $this->getEntityManager()->remove($styleSet);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?WysiwygStylesSet
    {
        $styleSet = $this->find($id);
        if (null !== $styleSet && !$styleSet instanceof WysiwygStylesSet) {
            throw new \RuntimeException('Unexpected wysiwyg style set type');
        }

        return $styleSet;
    }

    public function getByName(string $name): ?WysiwygStylesSet
    {
        $styleSet = $this->findOneBy(['name' => $name]);
        if (null !== $styleSet && !$styleSet instanceof WysiwygStylesSet) {
            throw new \RuntimeException('Unexpected style set type');
        }

        return $styleSet;
    }

    /**
     * @return WysiwygStylesSet[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('styleset')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name'])) {
            $qb->orderBy(\sprintf('styleset.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('styleset.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('styleset.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('styleset');
        $qb->select('count(styleset.id)');
        $this->addSearchFilters($qb, $searchValue);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }
}
