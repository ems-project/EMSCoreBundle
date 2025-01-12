<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Form;

/**
 * @extends ServiceEntityRepository<Form>
 *
 * @method Form|null find($id, $lockMode = null, $lockVersion = null)
 * @method Form|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method Form[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class FormRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Form::class);
    }

    /**
     * @return Form[]
     */
    public function getAll(): array
    {
        return $this->findBy([], ['orderKey' => 'ASC']);
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('count(c.id)');
        $this->addSearchFilters($qb, $searchValue);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }

    public function create(Form $form): void
    {
        $this->getEntityManager()->persist($form);
        $this->getEntityManager()->flush();
    }

    public function delete(Form $form): void
    {
        $this->getEntityManager()->remove($form);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return Form[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('form');
        $queryBuilder->where('form.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): Form
    {
        if (null === $form = $this->find($id)) {
            throw new \RuntimeException('Unexpected form type');
        }

        return $form;
    }

    /**
     * @return Form[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('c')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['label', 'name'])) {
            $qb->orderBy(\sprintf('c.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('c.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('c.label', ':term'),
                $qb->expr()->like('c.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function getByName(string $name): ?Form
    {
        $qb = $this->createQueryBuilder('form');
        $qb
            ->addSelect('fieldType')
            ->leftJoin('form.fieldType', 'fieldType')
            ->andWhere($qb->expr()->eq('form.name', ':name'))
            ->setParameter('name', $name);

        $form = $qb->getQuery()->getOneOrNullResult();

        return $form instanceof Form ? $form : null;
    }
}
