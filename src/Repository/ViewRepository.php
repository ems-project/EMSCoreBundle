<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\View;

/**
 * @extends ServiceEntityRepository<View>
 *
 * @method View|null find($id)
 * @method View|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
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

    public function counter(ContentType $contentType, string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('view');
        $qb->select('count(view.id)');
        $qb->where('view.contentType = :contentType')
            ->setParameter('contentType', $contentType);
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
     * @return View[]
     */
    public function getByIds(string ...$ids): array
    {
        $qb = $this->createQueryBuilder('v');
        $qb->andWhere('v.id IN (:ids)')->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        return $qb->getQuery()->getResult();
    }

    public function getById(string $id): View
    {
        $view = $this->find($id);

        if (null === $view) {
            throw new \RuntimeException('Unexpected view type');
        }

        return $view;
    }

    /**
     * @return View[]
     */
    public function get(ContentType $contentType, int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('view')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $qb->where('view.contentType = :contentType')
            ->setParameter('contentType', $contentType);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name'])) {
            $qb->orderBy(\sprintf('view.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('view.orderKey', $orderDirection);
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
