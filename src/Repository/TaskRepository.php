<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\Revision\Task\TaskTableContext;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Entity\UserInterface;

final class TaskRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function countForOwner(UserInterface $user): int
    {
        $qb = $this->_em->createQueryBuilder();
        $qb
            ->select('count(r.id)')
            ->from(Revision::class, 'r')
            ->andWhere($qb->expr()->eq('r.owner', ':username'))
            ->setParameter('username', $user->getUsername());

        return \intval($qb->getQuery()->getSingleScalarResult());
    }

    public function countForUser(UserInterface $user): int
    {
        $qb = $this->createQueryBuilder('t');
        $qb
            ->select('count(t.id)')
            ->andWhere($qb->expr()->eq('t.assignee', ':username'))
            ->setParameter('username', $user->getUsername());

        return \intval($qb->getQuery()->getSingleScalarResult());
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

    public function countTable(string $searchValue, TaskTableContext $context): int
    {
        $qb = $this->getTableQueryBuilder($searchValue, $context);
        $qb->select('count(r.id)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Revision[]
     */
    public function findTable(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, TaskTableContext $context): array
    {
        $qb = $this->getTableQueryBuilder($searchValue, $context);
        $qb
            ->setFirstResult($from)
            ->setMaxResults($size);

        if ($orderField && \array_key_exists($orderField, $context->columns)) {
            $qb->orderBy($context->columns[$orderField], $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    private function getTableQueryBuilder(string $searchValue, TaskTableContext $context): QueryBuilder
    {
        $qb = $this->_em->createQueryBuilder();
        $qb
            ->select('r', 't')
            ->from(Revision::class, 'r')
            ->join('r.taskCurrent', 't');

        switch ($context->tab) {
            case TaskManager::TAB_USER:
                $qb
                    ->andWhere($qb->expr()->eq('t.assignee', ':username'))
                    ->setParameter('username', $context->user->getUsername());
                break;
            case TaskManager::TAB_OWNER:
                $qb
                    ->andWhere($qb->expr()->eq('r.owner', ':username'))
                    ->setParameter('username', $context->user->getUsername());
                break;
        }

        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX();

            foreach ($context->columns as $col) {
                if ('t.deadline' !== $col) {
                    $or->add($qb->expr()->like($col, ':term'));
                }
            }
            if ($or->count() > 0) {
                $qb->andWhere($or)->setParameter(':term', '%'.$searchValue.'%');
            }
        }

        return $qb;
    }

    public function findTaskById(string $id): Task
    {
        $qb = $this->createQueryBuilder('t');
        $qb
            ->andWhere($qb->expr()->eq('t.id', ':id'))
            ->setParameter('id', $id);

        if (null === $task = $qb->getQuery()->getOneOrNullResult()) {
            throw new \RuntimeException(\sprintf('Task with id "%s" not found!', $id));
        }

        return $task;
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
            ->andWhere($subQuery->expr()->isNotNull('r.taskCurrent'));

        $qb = $this->_em->createQueryBuilder();
        $qb->select('c')->from(ContentType::class, 'c')
            ->andWhere($qb->expr()->in('c.id', $subQuery->getDQL()));

        return $qb->getQuery()->getResult();
    }
}
