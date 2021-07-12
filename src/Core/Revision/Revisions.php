<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Entity\Revision;

/**
 * @implements \IteratorAggregate<int, Revision>
 */
final class Revisions implements \IteratorAggregate
{
    private EntityManager $entityManager;
    private QueryBuilder $qb;

    public function __construct(QueryBuilder $qb)
    {
        $this->entityManager = $qb->getEntityManager();
        $this->qb = $qb;
    }

    /**
     * @return \Generator|Revision[]
     */
    public function getIterator(): \Generator
    {
        $iterator = $this->qb->getQuery()->iterate();

        foreach ($iterator as $revision) {
            yield $revision[0];
        }
    }

    public function batch(callable $batch, int $size = 250): void
    {
        $totalProcessed = 0;

        foreach ($this->getIterator() as $revision) {
            $batch($revision);
            $this->entityManager->persist($revision);

            if (0 === ++$totalProcessed % $size) {
                $this->entityManager->clear();
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
    }
}
