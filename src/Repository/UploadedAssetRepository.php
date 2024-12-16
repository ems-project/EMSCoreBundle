<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\UploadedAsset;

/**
 * @extends EntityRepository<UploadedAsset>
 *
 * @method UploadedAsset findOneBy(array $criteria, array $orderBy = null)
 */
class UploadedAssetRepository extends EntityRepository
{
    final public const PAGE_SIZE = 100;

    /**
     * @return int
     */
    public function countHashes()
    {
        $qb = $this->createQueryBuilder('ua');
        $qb->select('count(DISTINCT ua.sha1)')
            ->where($qb->expr()->eq('ua.available', ':true'));
        $qb->setParameters([
            ':true' => true,
        ]);

        try {
            return \intval($qb->getQuery()->getSingleScalarResult());
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * @return array<array{hash:string}>
     */
    public function getHashes(int $page): array
    {
        $qb = $this->createQueryBuilder('ua');
        $qb->select('ua.sha1 as hash')
            ->where($qb->expr()->eq('ua.available', ':true'))
            ->orderBy('ua.sha1', 'ASC')
            ->groupBy('ua.sha1')
            ->setFirstResult(UploadedAssetRepository::PAGE_SIZE * $page)
            ->setMaxResults(UploadedAssetRepository::PAGE_SIZE);
        $qb->setParameters([
            ':true' => true,
        ]);

        $out = [];
        foreach ($qb->getQuery()->getArrayResult() as $record) {
            if (isset($record['hash']) && \is_string($record['hash'])) {
                $out[] = ['hash' => $record['hash']];
            }
        }

        return $out;
    }

    /**
     * @param string $hash
     *
     * @return mixed
     */
    public function dereference($hash)
    {
        $qb = $this->createQueryBuilder('ua');
        $qb->update()
            ->set('ua.available', ':false')
            ->set('ua.status', ':status')
            ->where($qb->expr()->eq('ua.available', ':true'))
            ->andWhere($qb->expr()->eq('ua.sha1', ':hash'));
        $qb->setParameters([
            ':true' => true,
            ':false' => false,
            ':hash' => $hash,
            ':status' => 'cleaned',
        ]);

        return $qb->getQuery()->execute();
    }

    public function getInProgress(string $hash, string $user): ?UploadedAsset
    {
        return $this->findOneBy([
            'sha1' => $hash,
            'available' => false,
            'user' => $user,
        ]);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<UploadedAsset>
     */
    public function findByIds(array $ids): array
    {
        $qb = $this->createQueryBuilder('ua');
        $qb
            ->andWhere($qb->expr()->in('ua.id', $ids))
            ->orderBy('ua.created', 'desc');

        return $qb->getQuery()->execute();
    }

    public function remove(UploadedAsset $uploadedAsset): void
    {
        $this->_em->remove($uploadedAsset);
        $this->_em->flush();
    }

    public function getLastUploadedByHash(string $hash): ?UploadedAsset
    {
        $qb = $this->createQueryBuilder('ua');
        $qb->where($qb->expr()->eq('ua.available', ':true'));
        $qb->andWhere($qb->expr()->eq('ua.sha1', ':hash'));
        $qb->setParameters([
            ':true' => true,
            ':hash' => $hash,
        ]);
        $qb->orderBy('ua.modified', 'DESC');
        $qb->setMaxResults(1);
        $uploadedAsset = $qb->getQuery()->getOneOrNullResult();

        if (null === $uploadedAsset || $uploadedAsset instanceof UploadedAsset) {
            return $uploadedAsset;
        }
        throw new \RuntimeException(\sprintf('Unexpected class object %s', UploadedAsset::class));
    }

    public function update(UploadedAsset $UploadedAsset): void
    {
        $this->getEntityManager()->persist($UploadedAsset);
        $this->getEntityManager()->flush();
    }

    public function toggleVisibility(string $id): void
    {
        $uploadedAsset = $this->findOneBy([
            'id' => $id,
        ]);
        if (!$uploadedAsset instanceof UploadedAsset) {
            throw new \RuntimeException('Unexpected non UploadedAsset onject');
        }
        $uploadedAsset->setHidden(!$uploadedAsset->isHidden());
        $this->update($uploadedAsset);
    }

    /**
     * @param string[] $hashes
     */
    public function hideByHashes(array $hashes): int
    {
        $qb = $this->createQueryBuilder('ua')->update()
            ->set('ua.hidden', ':true')
            ->where('ua.sha1 IN (:hashes)')
            ->setParameters([
                ':hashes' => $hashes,
                ':true' => true,
            ]);

        return \intval($qb->getQuery()->execute());
    }

    /**
     * @param string[] $hashes
     *
     * @return string[]
     */
    public function hashesToIds(array $hashes): array
    {
        $qb = $this->createQueryBuilder('ua');
        $qb->select('max(ua.id) as id');
        $qb->where('ua.sha1 IN (:hashes)');
        $qb->andWhere($qb->expr()->eq('ua.hidden', ':false'));
        $qb->andWhere($qb->expr()->eq('ua.available', ':true'));
        $qb->setParameters([
            ':false' => false,
            ':true' => true,
            ':hashes' => $hashes,
        ]);

        $qb->groupBy('ua.sha1');

        return \array_map(fn ($value): string => \strval($value['id'] ?? null), $qb->getQuery()->getScalarResult());
    }

    public function makeQueryBuilder(
        ?bool $hidden = null,
        ?bool $available = null,
        string $searchValue = '',
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('ua');

        if (null !== $hidden) {
            $qb->andWhere($qb->expr()->eq('ua.hidden', $qb->expr()->literal($hidden)));
        }
        if (null !== $available) {
            $qb->andWhere($qb->expr()->eq('ua.available', $qb->expr()->literal($available)));
        }

        if ('' !== $searchValue) {
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('LOWER(ua.user)', ':term'),
                    $qb->expr()->like('LOWER(ua.sha1)', ':term'),
                    $qb->expr()->like('LOWER(ua.type)', ':term'),
                    $qb->expr()->like('LOWER(ua.name)', ':term')
                ))
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }

        return $qb;
    }
}
