<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Core\Doctrine\SimpleBatchIteratorAggregate;
use EMS\CoreBundle\Entity\Revision;
use EMS\Helpers\Standard\Type;

/**
 * @implements \IteratorAggregate<int, Revision>
 */
final class Revisions implements \IteratorAggregate
{
    /**
     * @param int<1, max> $batchSize
     */
    public function __construct(private readonly QueryBuilder $qb, private int $batchSize = 50)
    {
    }

    /**
     * @param int<1, max> $batchSize
     */
    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    /**
     * @return int[]
     */
    public function getIds(): array
    {
        $qb = clone $this->qb;
        $qb->select('r.id');

        $results = $qb->getQuery()->getScalarResult();

        return \array_map(fn (array $result) => $result['id'], $results);
    }

    /**
     * @return \Traversable<int, Revision>
     */
    public function getIterator(): \Traversable
    {
        /** @var \Traversable<int, Revision> $iterator */
        $iterator = SimpleBatchIteratorAggregate::fromQuery(
            $this->qb->getQuery(),
            $this->batchSize
        );

        return $iterator;
    }

    public function batch(callable $batch): void
    {
        foreach ($this->getIterator() as $revision) {
            $batch($revision);
        }
    }

    public function count(): int
    {
        $qb = clone $this->qb;
        $qb->select('count(r.id)');
        $count = Type::integer($qb->getQuery()->getSingleScalarResult());

        return $count;
    }
}
