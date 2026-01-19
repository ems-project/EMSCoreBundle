<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\SubmissionBundle\Entity\FormSubmission;

/**
 * @extends ServiceEntityRepository<FormSubmission>
 *
 * @method FormSubmission|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
class FormSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, FormSubmission::class);
    }

    public function findById(string $id): ?FormSubmission
    {
        $submission = $this->findOneBy(['id' => $id]);

        return $submission instanceof FormSubmission ? $submission : null;
    }

    /**
     * @return FormSubmission[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('fs');
        $qb->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['id', 'instance', 'name', 'locale', 'created', 'expireDate'])) {
            $qb->orderBy(\sprintf('fs.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('fs.created', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return \Generator<FormSubmission>
     */
    public function findAllUnprocessed(int $batchSize = 500): \Generator
    {
        $em = $this->getEntityManager();
        $offset = 0;
        while (true) {
            $query = $this->createQueryBuilder('fs')
                ->andWhere('fs.data IS NOT NULL')
                ->orderBy('fs.created', 'DESC')
                ->addOrderBy('fs.id', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery();
            $page = $query->getResult();

            if ([] === $page) {
                break;
            }

            foreach ($page as $entity) {
                yield $entity;
            }
            $em->clear();
            $offset += $batchSize;
        }
    }

    public function countAllUnprocessed(string $searchValue): int
    {
        $qb = $this->createQueryBuilder('fs');
        $qb->select('count(fs.id)');
        $this->addSearchFilters($qb, $searchValue);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function deleteAllExpiredSubmission(): int
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('fs')
            ->delete()
            ->where('fs.expireDate < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    public function clearDataOnExpiredSubmissions(): int
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('fs')
            ->update()
            ->set('fs.data', ':nullValue')
            ->where('fs.expireDate < :now')
            ->andWhere('fs.data IS NOT NULL')
            ->setParameter('now', $now)
            ->setParameter('nullValue', null)
            ->getQuery()
            ->execute();
    }

    /**
     * @return FormSubmission[]
     */
    public function findFormSubmissions(?string $formInstance = null): array
    {
        $qb = $this->createQueryBuilder('fs');

        if ($formInstance) {
            $qb->andWhere('fs.name = :name')
            ->setParameter('name', $formInstance);
        }

        $qb
            ->andWhere($qb->expr()->isNotNull('fs.data'))
            ->orderBy('fs.created', 'desc');

        return $qb->getQuery()->execute();
    }

    public function persist(FormSubmission $formSubmission): void
    {
        $this->getEntityManager()->persist($formSubmission);
    }

    public function save(FormSubmission $formSubmission): void
    {
        $this->getEntityManager()->persist($formSubmission);
        $this->getEntityManager()->flush();
    }

    public function remove(FormSubmission $formSubmission): void
    {
        $this->getEntityManager()->remove($formSubmission);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        $qb->andWhere($qb->expr()->isNotNull('fs.data'));
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('fs.id', ':term'),
                $qb->expr()->like('fs.instance', ':term'),
                $qb->expr()->like('fs.name', ':term'),
                $qb->expr()->like('fs.locale', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
