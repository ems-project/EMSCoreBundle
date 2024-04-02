<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\WysiwygProfile;

/**
 * @extends ServiceEntityRepository<WysiwygProfile>
 *
 * @method WysiwygProfile|null find($id)
 * @method WysiwygProfile|null findOneBy(array $criteria, array $orderBy = null)
 */
class WysiwygProfileRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, WysiwygProfile::class);
    }

    /**
     * @return WysiwygProfile[]
     */
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }

    public function update(WysiwygProfile $profile): void
    {
        $this->getEntityManager()->persist($profile);
        $this->getEntityManager()->flush();
    }

    public function delete(WysiwygProfile $profile): void
    {
        $this->getEntityManager()->remove($profile);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string[] $ids
     *
     * @return WysiwygProfile[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('wysiwyg_profile');
        $queryBuilder->where('wysiwyg_profile.id IN (:ids)')
            ->setParameter('ids', $ids);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findById(int $id): ?WysiwygProfile
    {
        return $this->find($id);
    }

    public function getByName(string $name): ?WysiwygProfile
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function getById(string $id): WysiwygProfile
    {
        if (null === $wysiwygProfile = $this->find($id)) {
            throw new \RuntimeException('Unexpected WysiwygProfile type');
        }

        return $wysiwygProfile;
    }

    public function create(WysiwygProfile $wysiwygProfile): void
    {
        $this->getEntityManager()->persist($wysiwygProfile);
        $this->getEntityManager()->flush();
    }

    /**
     * @return WysiwygProfile[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('profile')
            ->setFirstResult($from)
            ->setMaxResults($size);
        $this->addSearchFilters($qb, $searchValue);

        if (\in_array($orderField, ['name'])) {
            $qb->orderBy(\sprintf('profile.%s', $orderField), $orderDirection);
        } else {
            $qb->orderBy('profile.orderKey', $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function addSearchFilters(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('profile.name', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.$searchValue.'%');
        }
    }

    public function counter(string $searchValue = ''): int
    {
        $qb = $this->createQueryBuilder('profile');
        $qb->select('count(profile.id)');
        $this->addSearchFilters($qb, $searchValue);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }
}
