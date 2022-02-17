<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\WysiwygProfile;

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

    public function findById(int $id): ?WysiwygProfile
    {
        $wysiwygProfile = $this->find($id);
        if (null !== $wysiwygProfile && !$wysiwygProfile instanceof WysiwygProfile) {
            throw new \RuntimeException('Unexpected wysiwyg profile type');
        }

        return $wysiwygProfile;
    }

    public function getByName(string $name): ?WysiwygProfile
    {
        $profile = $this->findOneBy(['name' => $name]);
        if (null !== $profile && !$profile instanceof WysiwygProfile) {
            throw new \RuntimeException('Unexpected profile type');
        }

        return $profile;
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
