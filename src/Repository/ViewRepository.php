<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\View;

class ViewRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, View::class);
    }

    /**
     * @return View[]
     */
    public function getAll(ContentType $contentType): array
    {
        return $this->findBy([
            'contentType' => $contentType,
        ], [
            'orderKey' => 'ASC',
        ]);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('view');
        $qb->select('count(view.id)');
        $this->addSearchFilters($qb, $searchValue);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }

    public function create(View $view): void
    {
        $this->getEntityManager()->persist($view);
        $this->getEntityManager()->flush();
    }

    public function delete(View $view): void
    {
        $this->getEntityManager()->remove($view);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return View[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('view');
        $queryBuilder->where('view.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): View
    {
        $view = $this->find($id);
        if (!$view instanceof View) {
            throw new \RuntimeException('Unexpected view type');
        }

        return $view;
    }

    /**
     * @return View[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('view')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name'])) {
            $qb->orderBy(\sprintf('view.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('c.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('view.label', ':term'),
                $qb->expr()->like('view.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
