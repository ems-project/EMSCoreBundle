<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;

/**
 * @extends ServiceEntityRepository<Task>
 */
final class TaskRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function countApproved(Revision $revision): int
    {
        $qb = $this->createQueryBuilder('t');
        $qb
            ->select('count(t.id)')
            ->andWhere($qb->expr()->in('t.id', ':approved_ids'))
            ->setParameter('approved_ids', $revision->getTaskApprovedIds(), ArrayParameterType::STRING);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string[] $ids
     *
     * @return Task[]
     */
    public function findTasksByIds(array $ids): array
    {
        $qb = $this->createQueryBuilder('t');
        $qb
            ->andWhere($qb->expr()->in('t.id', ':ids'))
            ->setParameter('ids', \array_values($ids), ArrayParameterType::STRING);

        $tasks = \array_fill_keys($ids, null);
        foreach ($qb->getQuery()->getResult() as $task) {
            if ($task instanceof Task) {
                $tasks[$task->getId()] = $task;
            }
        }

        return \array_filter($tasks);
    }

    public function delete(Task $task): void
    {
        $this->getEntityManager()->remove($task);
        $this->getEntityManager()->flush();
    }

    /**
     * @return array<mixed>
     */
    public function update(Task $task): array
    {
        $uow = $this->getEntityManager()->getUnitOfWork();
        $uow->computeChangeSets();

        $changeSet = $uow->getEntityChangeSet($task);

        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();

        return $changeSet;
    }

    public function save(Task $task): void
    {
        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();
    }

    /**
     * @return ArrayCollection<int, ContentType>
     */
    public function findTaskContentTypes(): ArrayCollection
    {
        $subQuery = $this->getEntityManager()->createQueryBuilder();
        $subQuery
            ->select('rc.id')
            ->from(Revision::class, 'r')
            ->join('r.contentType', 'rc')
            ->andWhere('r.endTime is null')
            ->andWhere($subQuery->expr()->eq('r.deleted', ':false'))
            ->andWhere($subQuery->expr()->isNotNull('r.taskCurrent'));

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('c')->from(ContentType::class, 'c')
            ->andWhere($qb->expr()->in('c.id', $subQuery->getDQL()));
        $qb->setParameter(':false', false);

        return new ArrayCollection($qb->getQuery()->getResult());
    }
}
