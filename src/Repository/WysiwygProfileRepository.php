<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\WysiwygProfile;

/**
 * @extends ServiceEntityRepository<WysiwygProfile>
 *
 * @method WysiwygProfile|null find($id)
 * @method WysiwygProfile|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 */
class WysiwygProfileRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, WysiwygProfile::class);
    }

    public function delete(WysiwygProfile $profile): void
    {
        $this->getEntityManager()->remove($profile);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?WysiwygProfile
    {
        return $this->find($id);
    }

    public function getById(string $id): WysiwygProfile
    {
        if (null === $wysiwygProfile = $this->find($id)) {
            throw new \RuntimeException('Unexpected WysiwygProfile type');
        }

        return $wysiwygProfile;
    }

    /**
     * @return WysiwygProfile[]
     */
    public function getByIds(string ...$ids): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb
            ->andWhere($qb->expr()->in('p.id', ':ids'))
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        return $qb->getQuery()->getResult();
    }

    public function getByName(string $name): ?WysiwygProfile
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function makeQueryBuilder(string $searchValue = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        if ('' !== $searchValue) {
            $qb
                ->andWhere($qb->expr()->like('p.name', ':term'))
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }

        return $qb;
    }

    public function update(WysiwygProfile $profile): void
    {
        $this->getEntityManager()->persist($profile);
        $this->getEntityManager()->flush();
    }
}
