<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\Revision;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends EntityRepository<Revision>
 *
 * @method Revision|null findOneBy(array $criteria, array $orderBy = null)
 * @method Revision[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RevisionRepository extends EntityRepository
{
    public function findRevision(string $ouuid, ?string $contentTypeName = null, ?\DateTimeInterface $dateTime = null): ?Revision
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->addSelect('c')
            ->join('r.contentType', 'c')
            ->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))
            ->setParameter('ouuid', $ouuid)
            ->orderBy('r.startTime', 'DESC')
            ->setMaxResults(1);

        if ($contentTypeName) {
            $qb
                ->andWhere($qb->expr()->eq('c.name', ':content_type_name'))
                ->setParameter('content_type_name', $contentTypeName);
        }

        if (null === $dateTime) {
            $qb->andWhere($qb->expr()->isNull('r.endTime'));
        } else {
            $format = $this->getEntityManager()->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
            $qb
                ->andWhere($qb->expr()->lte('r.startTime', ':dateTime'))
                ->andWhere($qb->expr()->gte('r.endTime', ':dateTime'))
                ->setParameter('dateTime', $dateTime->format($format));
        }

        $result = $qb->getQuery()->getResult();

        return isset($result[0]) && $result[0] instanceof Revision ? $result[0] : null;
    }

    /**
     * @param array<mixed> $search
     */
    public function search(array $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->join('r.contentType', 'c')
            ->join('c.environment', 'e');

        if (isset($search['ouuid'])) {
            $qb->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))->setParameter('ouuid', $search['ouuid']);
        }
        if (isset($search['contentType'])) {
            $qb->andWhere($qb->expr()->eq('r.contentType', ':contentType'))->setParameter('contentType', $search['contentType']);
        }
        if (isset($search['contentTypeName'])) {
            $qb->andWhere($qb->expr()->eq('c.name', ':contentTypeName'))->setParameter('contentTypeName', $search['contentTypeName']);
        }
        if (isset($search['modifiedBefore'])) {
            $qb->andWhere($qb->expr()->lt('r.modified', ':modified'))->setParameter('modified', $search['modifiedBefore']);
        }
        if (isset($search['lockBy'])) {
            $qb->andWhere($qb->expr()->eq('r.lockBy', ':lock_by'))->setParameter('lock_by', $search['lockBy']);
        }
        if (isset($search['archived'])) {
            $qb->andWhere($qb->expr()->eq('r.archived', ':archived'))->setParameter('archived', $search['archived']);
        }
        if (isset($search['deleted'])) {
            $qb->andWhere($qb->expr()->eq('r.deleted', ':deleted'))->setParameter('deleted', $search['deleted']);
        }
        if (\array_key_exists('endTime', $search)) {
            if (null === $search['endTime']) {
                $qb->andWhere($qb->expr()->isNull('r.endTime'));
            } else {
                $qb->andWhere($qb->expr()->lt('r.endTime', ':endTime'))->setParameter('endTime', $search['endTime']);
            }
        }

        return $qb;
    }

    /**
     * @param string[] $ouuids
     */
    public function searchByEnvironmentOuuids(Environment $environment, array $ouuids): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->addSelect('ce, c')
            ->join('r.contentType', 'c')
            ->join('c.environment', 'ce')
            ->join('r.environments', 're')
            ->andWhere($qb->expr()->in('r.ouuid', ':ouuids'))
            ->andWhere($qb->expr()->in('re.id', ':environment_id'))
            ->setParameters([
                'environment_id' => $environment->getId(),
                'ouuids' => $ouuids,
            ]);

        return $qb;
    }

    public function save(Revision $revision): void
    {
        $this->_em->persist($revision);
        $this->_em->flush();
    }

    public function addEnvironment(Revision $revision, Environment $environment): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare('insert into environment_revision (environment_id, revision_id) VALUES(:envId, :revId)');
        $stmt->bindValue('envId', $environment->getId());
        $stmt->bindValue('revId', $revision->getId());

        return $stmt->executeStatement();
    }

    public function removeEnvironment(Revision $revision, Environment $environment): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare('delete from environment_revision where environment_id = :envId and revision_id = :revId');
        $stmt->bindValue('envId', $environment->getId());
        $stmt->bindValue('revId', $revision->getId());

        return $stmt->executeStatement();
    }

    /**
     * @return Paginator<Revision>
     */
    public function getRevisionsPaginatorPerEnvironment(Environment $env, int $page = 0): Paginator
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.environments', 'e')
        ->where('e.id = :eid')
        // ->andWhere($qb->expr()->eq('r.deleted', ':false')
        ->setMaxResults(50)
        ->setFirstResult($page * 50)
        ->orderBy('r.id', 'asc')
        ->setParameters(['eid' => $env->getId()]);

        return new Paginator($qb->getQuery());
    }

    public function findOneById(int $id): Revision
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.id = :id')
            ->setParameter('id', $id);

        return $qb->getQuery()->getSingleResult();
    }

    public function hashReferenced(string $hash): int
    {
        $connection = $this->getEntityManager()->getConnection();

        if ('postgresql' === $connection->getDatabasePlatform()->getName()) {
            $result = $this->getEntityManager()->getConnection()->fetchAllAssociative("select count(*) as counter FROM public.revision where raw_data::text like '%$hash%'");

            return \intval($result[0]['counter']);
        }

        try {
            $qb = $this->createQueryBuilder('r')
                ->select('count(r)')
                ->where('r.rawData like :hash')
                ->setParameter('hash', "%$hash%");
            $query = $qb->getQuery();

            return \intval($query->getSingleScalarResult());
        } catch (NonUniqueResultException) {
            throw new \RuntimeException(\sprintf('Revision with hash "%s" has non unique results!', $hash));
        }
    }

    /**
     * @return Paginator<Revision>
     */
    public function getRevisionsPaginatorPerEnvironmentAndContentType(Environment $env, ContentType $contentType, int $page = 0, int $size = 50): Paginator
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.environments', 'e')
        ->where('e.id = :eid')
        ->andWhere('r.contentType = :ct')
        ->setMaxResults($size)
        ->setFirstResult($page * $size)
        ->orderBy('r.id', 'asc')
        ->setParameters(['eid' => $env->getId(), 'ct' => $contentType]);

        return new Paginator($qb->getQuery());
    }

    public function findByEnvironment(string $ouuid, ContentType $contentType, Environment $environment): Revision
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.environments', 'e')
            ->andWhere('r.ouuid = :ouuid')
            ->andWhere('r.contentType = :contentType')
            ->andWhere('e.id = :eid')
            ->setParameter('ouuid', $ouuid)
            ->setParameter('contentType', $contentType)
            ->setParameter('eid', $environment->getId());

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * @param string[] $circles
     *
     * @return array<array{content_type_id: int, counter: int}>
     */
    public function draftCounterGroupedByContentType(array $circles, bool $isAdmin): array
    {
        $qb = $this->makeQueryBuilder(isDraft: true, isAdmin: $isAdmin, circles: $circles);
        $qb
            ->select('c.id content_type_id')
            ->addSelect('count(c.id) counter')
            ->groupBy('c.id');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param string[] $contentTypes
     */
    public function countDifferencesBetweenEnvironment(int $source, int $target, array $contentTypes = []): int
    {
        $sqb = $this->getCompareQueryBuilder($source, $target, $contentTypes);
        $sqb->select('max(r.id)');
        $qb = $this->createQueryBuilder('rev');
        $qb->select('count(rev)');
        $qb->where($qb->expr()->in('rev.id', $sqb->getDQL()));
        $qb->setParameters([
                'false' => false,
                'source' => $source,
                'target' => $target,
        ]);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string[] $contentTypes
     * @param string[] $ouuids
     */
    private function getCompareQueryBuilder(int $source, int $target, array $contentTypes, array $ouuids = [], string $searchValue = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('c.id', 'c.color', 'c.name content_type_name', 'c.singularName content_type_singular_name', 'c.icon', 'r.ouuid', "CONCAT(c.name, ':', r.ouuid) AS emsLink", 'max(r.labelField) as item_labelField', 'count(c.id) counter', 'min(concat(e.id, \'/\',r.id, \'/\', r.created, \'/\', r.finalizedBy)) minrevid', 'max(concat(e.id, \'/\',r.id, \'/\', r.created, \'/\', r.finalizedBy)) maxrevid', 'max(r.id) lastRevId')
        ->join('r.contentType', 'c')
        ->join('r.environments', 'e')
        ->where('e.id in (:source, :target)')
        ->andWhere($qb->expr()->eq('r.deleted', ':false'))
        ->andWhere($qb->expr()->eq('c.deleted', ':false'))
        ->groupBy('c.id', 'c.name', 'c.icon', 'r.ouuid', 'c.orderKey')
        ->orHaving('count(r.id) = 1')
        ->orHaving('max(r.id) <> min(r.id)')
        ->setParameters([
            'source' => $source,
            'target' => $target,
            'false' => false,
        ]);

        if (\count($ouuids) > 0) {
            $qb->andWhere($qb->expr()->notin('r.ouuid', $ouuids));
        }

        if (\strlen($searchValue) > 0) {
            $literal = $qb->expr()->literal('%'.\strtolower($searchValue).'%');
            $or = $qb->expr()->orX(
                $qb->expr()->like('LOWER(r.lockBy)', $literal),
                $qb->expr()->like('LOWER(r.autoSaveBy)', $literal),
                $qb->expr()->like('LOWER(r.labelField)', $literal),
            );
            $qb->andWhere($or);
        }

        if (!empty($contentTypes)) {
            $qb->andWhere('c.name in (\''.\implode("','", $contentTypes).'\')');
        }

        return $qb;
    }

    /**
     * @param string[] $contentTypes
     *
     * @return array<mixed>
     */
    public function compareEnvironment(int $source, int $target, array $contentTypes, int $from, int $limit, string $orderField = 'contenttype', string $orderDirection = 'ASC'): array
    {
        $orderField = match ($orderField) {
            'label' => 'item_labelField',
            default => 'c.name',
        };
        $qb = $this->getCompareQueryBuilder($source, $target, $contentTypes);
        $qb->addOrderBy($orderField, $orderDirection)
        ->addOrderBy('r.ouuid')
        ->setFirstResult($from)
        ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countByContentType(ContentType $contentType): int
    {
        return (int) $this->createQueryBuilder('a')
        ->select('COUNT(a)')
        ->where('a.contentType = :contentType')
        ->setParameter('contentType', $contentType)
        ->getQuery()
        ->getSingleScalarResult();
    }

    public function countRevisions(string $ouuid, ContentType $contentType): int
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->select('COUNT(r.id)')
            ->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))
            ->andWhere($qb->expr()->eq('r.contentType', ':contentType'))
            ->setParameters([
                'ouuid' => $ouuid,
                'contentType' => $contentType,
            ]);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function revisionsLastPage(string $ouuid, ContentType $contentType): int
    {
        return (int) \floor($this->countRevisions($ouuid, $contentType) / 5.0) + 1;
    }

    public function firstElemOfPage(int $page): int
    {
        return ($page - 1) * 5;
    }

    /**
     * @return Revision[]
     */
    public function getAllRevisionsSummary(string $ouuid, ContentType $contentType, int $page = 1): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('r', 'e');
        $qb->leftJoin('r.environments', 'e');
        $qb->where($qb->expr()->eq('r.ouuid', ':ouuid'));
        $qb->andWhere($qb->expr()->eq('r.contentType', ':contentType'));
        $qb->setMaxResults(5);
        $qb->setFirstResult(($page - 1) * 5);
        $qb->orderBy('r.created', 'DESC');
        $qb->setParameter('ouuid', $ouuid);
        $qb->setParameter('contentType', $contentType);

        return $qb->getQuery()->getResult();
    }

    public function findByOuuidContentTypeAndEnvironment(Revision $revision, Environment $env = null): ?Revision
    {
        $env ??= $revision->giveContentType()->giveEnvironment();

        return $this->findByOuuidAndContentTypeAndEnvironment($revision->giveContentType(), $revision->giveOuuid(), $env);
    }

    public function findByOuuidAndContentTypeAndEnvironment(ContentType $contentType, string $ouuid, Environment $env): ?Revision
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->join('r.environments', 'e')
            ->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))
            ->andWhere($qb->expr()->eq('e.id', ':envId'))
            ->andWhere($qb->expr()->eq('r.contentType', ':contentTypeId'))
            ->setParameters([
                'ouuid' => $ouuid,
                'envId' => $env->getId(),
                'contentTypeId' => $contentType->getId(),
            ]);

        $result = $qb->getQuery()->getResult();

        if ((\is_countable($result) ? \count($result) : 0) > 1) {
            throw new NonUniqueResultException($ouuid.' is publish multiple times in '.$env->getName());
        }

        if (isset($result[0]) && $result[0] instanceof Revision) {
            return $result[0];
        }

        return null;
    }

    /**
     * @return ?Revision[]
     */
    public function findIdByOuuidAndContentTypeAndEnvironment(string $ouuid, int $contentType, int $env): ?array
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.environments', 'e');
        $qb->where('r.ouuid = :ouuid and e.id = :envId and r.contentType = :contentTypeId');
        $qb->setParameters([
            'ouuid' => $ouuid,
            'envId' => $env,
            'contentTypeId' => $contentType,
        ]);

        $out = $qb->getQuery()->getArrayResult();
        if (\count($out) > 1) {
            throw new NonUniqueResultException($ouuid.' is publish multiple times in '.$env);
        }

        return $out[0] ?? null;
    }

    public function unlockRevision(int $revisionId): int
    {
        $qb = $this->createQueryBuilder('r')->update()
            ->set('r.lockBy', '?1')
            ->set('r.lockUntil', '?2')
            ->where('r.id = ?3')
            ->setParameter(1, null)
            ->setParameter(2, null)
            ->setParameter(3, $revisionId);

        return (int) $qb->getQuery()->execute();
    }

    public function finaliseRevision(ContentType $contentType, string $ouuid, \DateTime $now, string $lockUser): int
    {
        $qb = $this->createQueryBuilder('r')->update()
            ->set('r.endTime', '?1')
            ->where('r.contentType = ?2')
            ->andWhere('r.ouuid = ?3')
            ->andWhere('r.endTime is null')
            ->andWhere('r.lockBy  <> ?4 OR r.lockBy is null')
            ->setParameter(1, $now, Types::DATETIME_MUTABLE)
            ->setParameter(2, $contentType)
            ->setParameter(3, $ouuid)
            ->setParameter(4, $lockUser);

        return (int) $qb->getQuery()->execute();
    }

    public function getCurrentRevision(ContentType $contentType, string $ouuid): ?Revision
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))
            ->andWhere($qb->expr()->eq('r.contentType', ':contentType'))
            ->andWhere($qb->expr()->isNull('r.endTime'))
            ->setParameters([
                'ouuid' => $ouuid,
                'contentType' => $contentType,
            ]);

        $revision = $qb->getQuery()->getOneOrNullResult();

        return $revision instanceof Revision ? $revision : null;
    }

    public function publishRevision(Revision $revision, bool $draft = false): int
    {
        $qb = $this->createQueryBuilder('r')->update()
        ->set('r.draft', ':draft')
        ->set('r.lockBy', 'null')
        ->set('r.lockUntil', 'null')
        ->set('r.endTime', 'null')
        ->where('r.id = :id')
        ->setParameters([
                'draft' => $draft,
                'id' => $revision->getId(),
            ]);

        return (int) $qb->getQuery()->execute();
    }

    public function deleteOldest(ContentType $contentType, ?string $ouuid): int
    {
        $conn = $this->_em->getConnection();

        $qbSub = $conn->createQueryBuilder();
        $qbSub
            ->select('old.ouuid, min(old.start_time) as minStartTime')
            ->from('revision', 'old')
            ->join('old', 'environment_revision', 'er', 'er.revision_id = old.id')
            ->groupBy('old.ouuid');

        $qbSelect = $conn->createQueryBuilder();
        $qbSelect
            ->from('revision', 'r')
            ->innerJoin('r', \sprintf('(%s)', $qbSub->getSQL()), 'sub', 'sub.ouuid = r.ouuid')
            ->join('r', 'content_type', 'c', 'r.content_type_id = c.id')
            ->andWhere($qbSelect->expr()->lt('r.start_time', 'sub.minStartTime'))
            ->andWhere($qbSelect->expr()->eq('c.id', ':content_type_id'))
            ->setParameter('content_type_id', $contentType->getId());
        if (null !== $ouuid) {
            $qbSelect->andWhere($qbSelect->expr()->eq('r.ouuid', ':ouuid'))
                ->setParameter('ouuid', $ouuid);
        }

        return $this->deleteByQueryBuilder($qbSelect);
    }

    public function deleteByContentType(ContentType $contentType): int
    {
        $conn = $this->_em->getConnection();
        $qb = $conn->createQueryBuilder();
        $qb
            ->from('revision', 'r')
            ->join('r', 'content_type', 'c', 'r.content_type_id = c.id')
            ->andWhere($qb->expr()->eq('c.id', ':content_type_id'))
            ->setParameter('content_type_id', $contentType->getId());

        return $this->deleteByQueryBuilder($qb);
    }

    /**
     * @param string[] $ouuids
     */
    public function deleteByOuuids(array $ouuids): int
    {
        $conn = $this->_em->getConnection();
        $qb = $conn->createQueryBuilder();
        $qb
            ->from('revision', 'r')
            ->andWhere($qb->expr()->in('r.ouuid', ':ouuids'))
            ->setParameter('ouuids', $ouuids, Connection::PARAM_STR_ARRAY);

        return $this->deleteByQueryBuilder($qb);
    }

    public function lockRevisions(?ContentType $contentType, \DateTime $until, string $by, bool $force = false, ?string $ouuid = null, bool $onlyCurrentRevision = true): int
    {
        $qbSelect = $this->createQueryBuilder('s');
        $qbSelect
            ->select('s.id')
            ->andWhere($qbSelect->expr()->eq('s.deleted', $qbSelect->expr()->literal(false)))
            ->andWhere($qbSelect->expr()->eq('s.draft', $qbSelect->expr()->literal(false)))
        ;
        if ($onlyCurrentRevision) {
            $qbSelect->andWhere($qbSelect->expr()->isNull('s.endTime'));
        }

        $qbUpdate = $this->createQueryBuilder('r');
        $qbUpdate
            ->update()
            ->set('r.lockBy', ':by')
            ->set('r.lockUntil', ':until')
            ->setParameters(['by' => $by, 'until' => $until]);

        if (null !== $contentType) {
            $qbSelect->andWhere($qbSelect->expr()->eq('s.contentType', ':content_type'));
            $qbUpdate->setParameter('content_type', $contentType);
        }

        if (!$force) {
            $qbSelect->andWhere($qbSelect->expr()->orX(
                $qbSelect->expr()->lt('s.lockUntil', ':now'),
                $qbSelect->expr()->isNull('s.lockUntil')
            ));
            $qbUpdate->setParameter('now', new \DateTime());
        }

        if (null !== $ouuid) {
            $qbSelect->andWhere($qbSelect->expr()->eq('s.ouuid', ':ouuid'));
            $qbUpdate->setParameter('ouuid', $ouuid);
        }

        $qbUpdate->andWhere($qbUpdate->expr()->in('r.id', $qbSelect->getDQL()));

        return (int) $qbUpdate->getQuery()->execute();
    }

    /**
     * @param int[] $ids
     */
    public function lockRevisionsById(array $ids, string $by, \DateTime $until): int
    {
        $qbUpdate = $this->createQueryBuilder('r');
        $qbUpdate
            ->update()
            ->set('r.lockBy', ':by')
            ->set('r.lockUntil', ':until')
            ->andWhere($qbUpdate->expr()->in('r.id', ':ids'))
            ->setParameters(['ids' => $ids, 'by' => $by, 'until' => $until]);

        return $qbUpdate->getQuery()->execute();
    }

    public function lockAllRevisions(\DateTime $until, string $by): int
    {
        return $this->lockRevisions(null, $until, $by, true);
    }

    public function unlockRevisions(?ContentType $contentType, string $by, bool $onlyCurrentRevision = true): int
    {
        $qbSelect = $this->createQueryBuilder('s');
        $qbSelect
            ->select('s.id')
            ->andWhere($qbSelect->expr()->eq('s.lockBy', ':by'))
            ->andWhere($qbSelect->expr()->eq('s.deleted', $qbSelect->expr()->literal(false)))
            ->andWhere($qbSelect->expr()->eq('s.draft', $qbSelect->expr()->literal(false)))
        ;
        if ($onlyCurrentRevision) {
            $qbSelect->andWhere($qbSelect->expr()->isNull('s.endTime'));
        }

        $qbUpdate = $this->createQueryBuilder('u');
        $qbUpdate
            ->update()
            ->set('u.lockBy', ':null')
            ->set('u.lockUntil', ':null')
            ->setParameters(['by' => $by, 'null' => null])
        ;

        if (null !== $contentType) {
            $qbSelect->andWhere($qbSelect->expr()->eq('s.contentType', ':content_type'));
            $qbUpdate->setParameter('content_type', $contentType);
        }

        $qbUpdate->andWhere($qbUpdate->expr()->in('u.id', $qbSelect->getDQL()));

        return $qbUpdate->getQuery()->execute();
    }

    /**
     * @param int[] $ids
     */
    public function unlockRevisionsById(array $ids): int
    {
        $qbUpdate = $this->createQueryBuilder('r');
        $qbUpdate
            ->update()
            ->set('r.lockBy', ':null')
            ->set('r.lockUntil', ':null')
            ->andWhere($qbUpdate->expr()->in('r.id', ':ids'))
            ->setParameters(['ids' => $ids, 'null' => null]);

        return $qbUpdate->getQuery()->execute();
    }

    public function unlockAllRevisions(string $by): int
    {
        return $this->unlockRevisions(null, $by);
    }

    /**
     * @return Revision[]
     */
    public function findAllWithCurrentTask(?\DateTimeImmutable $deadlineStart = null, ?\DateTimeImmutable $deadlineEnd = null, TaskStatus ...$status): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->addSelect('r', 't')
            ->join('r.taskCurrent', 't')
            ->andWhere($qb->expr()->isNotNull('r.taskCurrent'))
            ->andWhere($qb->expr()->isNull('r.endTime'))
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->orderBy('t.deadline, t.status')
        ;

        if ($deadlineStart) {
            $qb
                ->andWhere($qb->expr()->gte('t.deadline', ':deadline_start'))
                ->setParameter('deadline_start', $deadlineStart->setTime(0, 0)->format(\DATE_ATOM));
        }

        if ($deadlineEnd) {
            $qb
                ->andWhere($qb->expr()->lte('t.deadline', ':deadline_end'))
                ->setParameter('deadline_end', $deadlineEnd->setTime(23, 59, 59)->format(\DATE_ATOM));
        }

        if (\count($status) > 0) {
            $statuses = \array_map(static fn (TaskStatus $s) => $s->value, $status);
            $qb
                ->andWhere($qb->expr()->in('t.status', ':status'))
                ->setParameter('status', $statuses, ArrayParameterType::STRING);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Paginator<Revision>
     */
    public function findAllLockedRevisions(ContentType $contentType, string $lockBy, int $page = 0, int $limit = 50): Paginator
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->andWhere($qb->expr()->eq('r.contentType', ':content_type'))
            ->andWhere($qb->expr()->eq('r.lockBy', ':username'))
            ->andWhere($qb->expr()->isNull('r.endTime'))
            ->setMaxResults($limit)
            ->setFirstResult($page * $limit)
            ->orderBy('r.id', 'asc')
            ->setParameters([
                'content_type' => $contentType,
                'username' => $lockBy,
            ])
        ;

        return new Paginator($qb->getQuery());
    }

    /**
     * @return array<string, Revision[]>
     */
    public function findAllPublishedRevision(EMSLink ...$emsIds): array
    {
        $ouuids = \array_map(static fn (EMSLink $link) => $link->getOuuid(), $emsIds);

        $qb = $this->createQueryBuilder('r');
        $qb
            ->addSelect('c')
            ->addSelect('e')
            ->join('r.contentType', 'c')
            ->join('r.environments', 'e')
            ->andWhere($qb->expr()->eq('c.active', $qb->expr()->literal(true)))
            ->andWhere($qb->expr()->eq('c.deleted', $qb->expr()->literal(false)))
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->andWhere($qb->expr()->isNotNull('e.id'))
            ->andWhere($qb->expr()->in('r.ouuid', ':ouuids'))
            ->setParameter('ouuids', $ouuids, ArrayParameterType::STRING);

        /** @var Revision[] $revisions */
        $revisions = $qb->getQuery()->getResult();
        $publishedRevisions = [];

        foreach ($revisions as $revision) {
            $publishedRevisions[$revision->getEmsId()][] = $revision;
        }

        return $publishedRevisions;
    }

    /**
     * @return \Traversable<int, string>
     */
    public function findAllOuuidsByContentTypeAndEnvironment(ContentType $contentType, Environment $environment): \Traversable
    {
        $connection = $this->_em->getConnection();

        $qb = $connection->createQueryBuilder();
        $qb->select('r.ouuid')
            ->from('revision', 'r')
            ->join('r', 'content_type', 'c', 'r.content_type_id = c.id')
            ->join('r', 'environment_revision', 'er', 'er.revision_id = r.id')
            ->andWhere($qb->expr()->eq('er.environment_id', ':environment_id'))
            ->andWhere($qb->expr()->eq('c.id', ':content_type_id'));

        return $connection->iterateColumn($qb->getSQL(), [
            'environment_id' => $environment->getId(),
            'content_type_id' => $contentType->getId(),
        ]);
    }

    /**
     * @return Revision[]
     */
    public function findAllDrafts(): array
    {
        return $this->makeQueryBuilder(isDraft: true)->orderBy('r.id', 'asc')->getQuery()->execute();
    }

    /**
     * @return Revision[]
     */
    public function findTrashRevisions(ContentType $contentType, string ...$ouuids): array
    {
        $qb = $this->makeQueryBuilder(
            contentTypeName: $contentType->getName(),
            isDeleted: true,
            isCurrent: null
        );

        $qb
            ->andWhere($qb->expr()->in('r.ouuid', ':ouuids'))
            ->orderBy('r.startTime', 'DESC')
            ->setParameter('ouuids', $ouuids, ArrayParameterType::STRING);

        return $qb->getQuery()->execute();
    }

    public function findLatestVersion(ContentType $contentType, string $versionOuuid, ?Environment $environment = null): ?Revision
    {
        $toField = $contentType->getVersionDateToField();

        $qb = $this->createQueryBuilder('r');
        $qb
            ->andWhere($qb->expr()->eq('r.versionUuid', ':version_ouuid'))
            ->setParameter('version_ouuid', $versionOuuid);

        if ($environment) {
            $qb
                ->join('r.environments', 'e')
                ->andWhere($qb->expr()->eq('e.id', ':environment_id'))
                ->setParameter('environment_id', $environment->getId());
        } else {
            $qb->andWhere($qb->expr()->isNull('r.endTime'));
        }

        /** @var Revision[] $revisions */
        $revisions = $qb->getQuery()->getResult();

        foreach ($revisions as $revision) {
            $toFieldValue = $revision->getRawData()[$toField] ?? 'latest';
            if ('latest' === $toFieldValue) {
                return $revision;
            }
        }

        return null;
    }

    public function findPreviousRevision(Revision $revision): ?Revision
    {
        return $this->findOneBy([
            'ouuid' => $revision->getOuuid(),
            'endTime' => $revision->getStartTime(),
            'draft' => false,
        ]);
    }

    /**
     * @param string[] $contentTypes
     *
     * @return Revision[]
     */
    public function getAvailableRevisionsForRelease(int $from, int $size, Release $release, array $contentTypes, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->getCompareQueryBuilder($release->getEnvironmentSource()->getId(), $release->getEnvironmentTarget()->getId(), $contentTypes, $release->getRevisionsOuuids(), $searchValue);
        if (null === $orderField) {
            $qb->orderBy('r.ouuid', $orderDirection);
        } else {
            $qb->orderBy('r.ouuid', $orderDirection);
        }
        $qb->setFirstResult($from)
            ->setMaxResults($size);

        return $qb->getQuery()->execute();
    }

    /**
     * @param string[] $contentTypes
     */
    public function countAvailableRevisionsForRelease(Release $release, array $contentTypes, string $searchValue): int
    {
        $sqb = $this->getCompareQueryBuilder($release->getEnvironmentSource()->getId(), $release->getEnvironmentTarget()->getId(), $contentTypes, $release->getRevisionsOuuids(), $searchValue);
        $sqb->select('max(r.id)');

        $qb = $this->createQueryBuilder('rev');
        $qb->select('count(rev)');
        $qb->where($qb->expr()->in('rev.id', $sqb->getDQL()));
        $qb->setParameters([
            'source' => $release->getEnvironmentSource()->getId(),
            'target' => $release->getEnvironmentTarget()->getId(),
            'false' => false,
        ]);
        $query = $qb->getQuery();

        return \intval($query->getSingleScalarResult());
    }

    /**
     * @return Revision[]
     */
    public function findAllByVersionUuid(UuidInterface $versionUuid, Environment $defaultEnvironment): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->addSelect('e')
            ->join('r.contentType', 'c')
            ->join('r.environments', 'e')
            ->andWhere($qb->expr()->eq('e.id', ':environment_id'))
            ->andWhere($qb->expr()->eq('r.versionUuid', ':version_uuid'))
            ->andWhere($qb->expr()->eq('c.deleted', $qb->expr()->literal(false)))
            ->andWhere($qb->expr()->eq('c.active', $qb->expr()->literal(true)))
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->setParameters([
                'version_uuid' => $versionUuid,
                'environment_id' => $defaultEnvironment->getId(),
            ]);

        return $qb->getQuery()->execute();
    }

    private function deleteByQueryBuilder(DBALQueryBuilder $queryBuilder): int
    {
        $conn = $this->_em->getConnection();
        $revisionIds = $queryBuilder->select('r.id')->getSQL();
        $revisionOuuids = $queryBuilder->select('r.ouuid')->getSQL();

        $qbDeleteNotifications = $conn->createQueryBuilder();
        $qbDeleteNotifications
            ->delete('notification')
            ->andWhere($qbDeleteNotifications->expr()->in('revision_id', $revisionIds));
        $this->copyParameters($qbDeleteNotifications, $queryBuilder);

        $qbDeleteNotifications->executeStatement();

        $qbUpdateRevisions = $conn->createQueryBuilder();
        $qbUpdateRevisions
            ->update('revision')
            ->set('task_current_id', 'null')
            ->set('task_planned_ids', 'null')
            ->set('task_approved_ids', 'null')
            ->andWhere($qbUpdateRevisions->expr()->in('id', $revisionIds));
        $this->copyParameters($qbUpdateRevisions, $queryBuilder);

        $qbUpdateRevisions->executeStatement();

        $qbDeleteTasks = $conn->createQueryBuilder();
        $qbDeleteTasks
            ->delete('task')
            ->andWhere($qbDeleteTasks->expr()->in('revision_ouuid', $revisionOuuids));
        $this->copyParameters($qbDeleteTasks, $queryBuilder);
        $qbDeleteTasks->executeStatement();

        $qbDelete = $conn->createQueryBuilder();
        $qbDelete
            ->delete('revision')
            ->andWhere($qbDelete->expr()->in('id', $revisionIds));
        $this->copyParameters($qbDelete, $queryBuilder);

        return $qbDelete->executeStatement();
    }

    public function switchEnvironments(ContentType $contentType, Environment $target, string $username, int $batchSize = 500): void
    {
        $this->lockRevisions($contentType, new \DateTime('+60 min'), $username, true, null, false);
        $qb = $this->createQueryBuilder('r')
            ->join('r.environments', 'e')
            ->where('r.contentType = :ct')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', [$contentType->giveEnvironment()->getId(), $target->getId()])
            ->setParameter('ct', $contentType);
        $detachableEntities = [];
        foreach ($qb->getQuery()->execute() as $revision) {
            if (!$revision instanceof Revision) {
                throw new \RuntimeException('Unexpected object');
            }
            if ($revision->getEnvironments()->contains($contentType->giveEnvironment()) && $revision->getEnvironments()->contains($target)) {
                continue;
            }
            if ($revision->getEnvironments()->contains($contentType->giveEnvironment())) {
                $revision->addEnvironment($target);
                $revision->removeEnvironment($contentType->giveEnvironment());
            } else {
                $revision->addEnvironment($contentType->giveEnvironment());
                $revision->removeEnvironment($target);
            }
            $detachableEntities[] = $revision;
            if ((\count($detachableEntities) % $batchSize) === 0) {
                $this->_em->flush();
                foreach ($detachableEntities as $detachableEntity) {
                    $this->_em->detach($detachableEntity);
                }
                $detachableEntities = [];
            }
        }
        $this->_em->flush();
        foreach ($detachableEntities as $detachableEntity) {
            $this->_em->detach($detachableEntity);
        }
        $this->unlockRevisions($contentType, $username, false);
    }

    private function copyParameters(DBALQueryBuilder $target, DBALQueryBuilder $source): void
    {
        foreach ($source->getParameters() as $key => $value) {
            $target->setParameter($key, $value, $source->getParameterType($key));
        }
    }

    /**
     * @param string[] $circles
     */
    public function makeQueryBuilder(
        string $contentTypeName = null,
        ?bool $isCurrent = true,
        ?bool $isDeleted = false,
        ?bool $isDraft = false,
        ?bool $isAdmin = null,
        array $circles = [],
        string $searchValue = ''
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.contentType', 'c');

        if ($contentTypeName) {
            $qb
                ->andWhere($qb->expr()->eq('c.name', ':content_type_name'))
                ->setParameter('content_type_name', $contentTypeName);
        }

        if ($isCurrent) {
            $qb->andWhere($qb->expr()->isNull('r.endTime'));
        }
        if (null !== $isDeleted) {
            $qb->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal($isDeleted)));
        }
        if (null !== $isDraft) {
            $qb->andWhere($qb->expr()->eq('r.draft', $qb->expr()->literal($isDraft)));
        }

        if (false === $isAdmin) {
            $inCircles = $qb->expr()->orX();
            $inCircles->add($qb->expr()->isNull('r.circles'));
            foreach ($circles as $counter => $circle) {
                $inCircles->add($qb->expr()->like('r.circles', ':circle'.$counter));
                $qb->setParameter('circle'.$counter, '%'.$circle.'%');
            }
            $qb->andWhere($inCircles);
        }

        if ('' !== $searchValue) {
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('LOWER(r.lockBy)', ':term'),
                    $qb->expr()->like('LOWER(r.autoSaveBy)', ':term'),
                    $qb->expr()->like('LOWER(r.labelField)', ':term'),
                ))
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }

        return $qb;
    }
}
