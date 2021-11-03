<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;

final class ReleaseRevisionRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, ReleaseRevision::class);
    }

    /**
     * @return Release[]
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
        ->setParameters([
            'releaseId' => $release->getId(),
            'ouuid' => $ouuid,
            'contentTypeId' => $contentType->getId(),
        ]);

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
        ->setParameters([
            'ouuid' => $ouuid,
            'contentType' => $contentType,
            'status' => [Release::WIP_STATUS, Release::READY_STATUS],
        ]);

        return $qb->getQuery()->execute();
    }

    /**
     * @return ReleaseRevision[]
     */
    public function findByRelease(Release $release, int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('rr');
        $qb->where($qb->expr()->eq('rr.release', ':release'))
            ->setParameters([
                'release' => $release,
            ]);
        $qb->orderBy(\sprintf('rr.%s', $orderField ?? 'id'), $orderDirection);
        $qb->setFirstResult($from);
        $qb->setMaxResults($size);

        return $qb->getQuery()->execute();
    }

    public function countByRelease(Release $release, string $searchValue): int
    {
        $qb = $this->createQueryBuilder('rr');
        $qb->where($qb->expr()->eq('rr.release', ':release'))
            ->setParameters([
                'release' => $release,
            ]);
        $qb->select('count(rr)');
        $query = $qb->getQuery();

        return \intval($query->getSingleScalarResult());
    }
}
