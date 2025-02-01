<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Query\Parameter;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;

/**
 * @extends ServiceEntityRepository<ReleaseRevision>
 *
 * @method ReleaseRevision[] findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
final class ReleaseRevisionRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, ReleaseRevision::class);
    }

    /**
     * @return ReleaseRevision[]
     */
    public function getAll(): array
    {
        return $this->findBy([]);
    }

    public function counter(): int
    {
        return parent::count([]);
    }

    public function create(ReleaseRevision $releaseRevision): void
    {
        $this->getEntityManager()->persist($releaseRevision);
        $this->getEntityManager()->flush();
    }

    public function delete(ReleaseRevision $releaseRevision): void
    {
        $this->getEntityManager()->remove($releaseRevision);
        $this->getEntityManager()->flush();
    }

    public function findByReleaseByRevisionOuuidAndContentType(Release $release, string $ouuid, ContentType $contentType): ReleaseRevision
    {
        $qb = $this->createQueryBuilder('r');
        $qb->where($qb->expr()->eq('r.release', ':releaseId'))
        ->andWhere($qb->expr()->eq('r.revisionOuuid', ':ouuid'))
        ->andWhere($qb->expr()->eq('r.contentType', ':contentTypeId'))
        ->setParameters(new ArrayCollection([
            new Parameter('releaseId', $release->getId()),
            new Parameter('ouuid', $ouuid),
            new Parameter('contentTypeId', $contentType->getId()),
        ]));

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * @return ReleaseRevision[]
     */
    public function getRevisionsLinkedToReleasesByOuuid(string $ouuid, ContentType $contentType): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.release', 'rel')
        ->where($qb->expr()->eq('r.revisionOuuid', ':ouuid'))
        ->andWhere($qb->expr()->eq('r.contentType', ':contentType'))
        ->andWhere('rel.status in (:status)')
        ->setParameters(new ArrayCollection([
            new Parameter('ouuid', $ouuid),
            new Parameter('contentType', $contentType),
            new Parameter('status', [Release::WIP_STATUS, Release::READY_STATUS], ArrayParameterType::STRING),
        ]));

        return $qb->getQuery()->execute();
    }

    /**
     * @return ReleaseRevision[]
     */
    public function findByRelease(Release $release, int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('rr');
        $qb
            ->join('rr.revision', 'r')
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->andWhere($qb->expr()->eq('rr.release', ':release'))
            ->orderBy(\sprintf('rr.%s', $orderField ?? 'id'), $orderDirection)
            ->setFirstResult($from)
            ->setMaxResults($size)
            ->setParameters(new ArrayCollection([
                new Parameter('release', $release),
            ]));

        return $qb->getQuery()->execute();
    }

    public function countByRelease(Release $release, string $searchValue): int
    {
        $qb = $this->createQueryBuilder('rr');
        $qb
            ->select('count(rr)')
            ->join('rr.revision', 'r')
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->andWhere($qb->expr()->eq('rr.release', ':release'))
            ->setParameters(new ArrayCollection([
                new Parameter('release', $release),
            ]));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string[] $ids
     *
     * @return ReleaseRevision[]
     */
    public function getByIds(array $ids): array
    {
        $queryBuilder = $this->createQueryBuilder('rr');
        $queryBuilder->where('rr.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getById(string $id): ReleaseRevision
    {
        $queryBuilder = $this->createQueryBuilder('rr');
        $queryBuilder->where('rr.id = :id')
            ->setParameter('id', $id);

        return $queryBuilder->getQuery()->getSingleResult();
    }
}
