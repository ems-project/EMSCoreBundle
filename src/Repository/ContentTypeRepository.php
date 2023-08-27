<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\ContentType;

/**
 * @extends EntityRepository<ContentType>
 *
 * @method ContentType|null findOneBy(array $criteria, array $orderBy = null)
 * @method ContentType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContentTypeRepository extends EntityRepository
{
    /**
     * @return ContentType[]
     */
    public function findAllAsAssociativeArray()
    {
        $qb = $this->createQueryBuilder('ct');
        $qb->where($qb->expr()->eq('ct.deleted', ':false'));
        $qb->setParameters([
            'false' => false,
        ]);

        $out = [];
        $result = $qb->getQuery()->getResult();
        /** @var ContentType $record */
        foreach ($result as $record) {
            $out[$record->getName()] = $record;
        }

        return $out;
    }

    /**
     * @return ContentType[]
     */
    public function findAll()
    {
        return parent::findBy(['deleted' => false], ['orderKey' => 'ASC']);
    }

    public function findByName(string $name): ?ContentType
    {
        return $this->findOneBy(['deleted' => false, 'name' => $name]);
    }

    public function findById(int $id): ?ContentType
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function countContentType(): int
    {
        return (int) $this->createQueryBuilder('a')
         ->select('COUNT(a)')
         ->getQuery()
         ->getSingleScalarResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function nextOrderKey(): int
    {
        $max = (int) $this->createQueryBuilder('a')
         ->select('max(a.orderKey)')
         ->getQuery()
         ->getSingleScalarResult();

        return $max + 1;
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('count(c.id)');
        $this->addSearchFilters($qb, $searchValue);

        try {
            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * @return ContentType[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('c')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name', 'pluralName', 'singularName', 'active'])) {
            $qb->orderBy(\sprintf('c.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('c.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    public function delete(ContentType $contentType): void
    {
        $fieldType = $contentType->getFieldType();
        $contentType->unsetFieldType();
        $this->getEntityManager()->persist($contentType);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->remove($fieldType);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->remove($contentType);
        $this->getEntityManager()->flush();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        $qb->where($qb->expr()->eq('c.deleted', ':false'));
        $qb->setParameter(':false', false);
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('c.label', ':term'),
                $qb->expr()->like('c.pluralName', ':term'),
                $qb->expr()->like('c.singularName', ':term')
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }
}
