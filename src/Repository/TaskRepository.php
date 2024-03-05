<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
            ->setParameter('approved_ids', $revision->getTaskApprovedIds());

        return \intval($qb->getQuery()->getSingleScalarResult());
    }

    public function hasVersionedContentType(): bool
    {
        $contentTypes = $this->findTaskContentTypes();

        foreach ($contentTypes as $contentType) {
            if ($contentType->hasVersionTags()) {
                return true;
            }
        }

        return false;
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
            ->setParameter('ids', \array_values($ids));

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
        $this->_em->remove($task);
        $this->_em->flush();
    }

    /**
     * @return array<mixed>
     */
    public function update(Task $task): array
    {
        $uow = $this->_em->getUnitOfWork();
        $uow->computeChangeSets();

        $changeSet = $uow->getEntityChangeSet($task);

        $this->_em->persist($task);
        $this->_em->flush();

        return $changeSet;
    }

    public function save(Task $task): void
    {
        $this->_em->persist($task);
        $this->_em->flush();
    }

    /**
     * @return ContentType[]
     */
    private function findTaskContentTypes(): array
    {
        $subQuery = $this->_em->createQueryBuilder();
        $subQuery
            ->select('rc.id')
            ->from(Revision::class, 'r')
            ->join('r.contentType', 'rc')
            ->andWhere('r.endTime is null')
            ->andWhere($subQuery->expr()->eq('r.deleted', ':false'))
            ->andWhere($subQuery->expr()->isNotNull('r.taskCurrent'));

        $qb = $this->_em->createQueryBuilder();
        $qb->select('c')->from(ContentType::class, 'c')
            ->andWhere($qb->expr()->in('c.id', $subQuery->getDQL()));
        $qb->setParameter(':false', false);

        return $qb->getQuery()->getResult();
    }
}
