<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;

final class TaskRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return ArrayCollection<string, Task>
     */
    public function getTasks(Revision $revision): ArrayCollection
    {
        $qb = $this->createQueryBuilder('t');
        $revisionTasks = $revision->getTasks();

        $orExpr = $qb->expr()->orX();

        if ($revisionTasks->hasCurrentId()) {
            $orExpr->add($qb->expr()->eq('t.id', ':current_id'));
            $qb->setParameter('current_id', $revisionTasks->getCurrentId());
        }

        if ($revisionTasks->hasPlannedIds()) {
            $orExpr->add($qb->expr()->in('t.id', ':planned_ids'));
            $qb->setParameter('planned_ids', $revisionTasks->getPlannedIds());
        }

        if ($orExpr->count() > 0) {
            $qb->andWhere($orExpr);
        }

        /** @var Task[] $results */
        $results = $qb->getQuery()->getResult();

        $collection = new ArrayCollection();

        foreach ($results as $result) {
            $collection->set($result->getId(), $result);
        }

        return $collection;
    }

    public function delete(Task $task): void
    {
        $this->_em->remove($task);
        $this->_em->flush();
    }

    public function save(Task $task): void
    {
        $this->_em->persist($task);
        $this->_em->flush();
    }
}
