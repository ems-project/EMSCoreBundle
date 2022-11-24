<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use Doctrine\ORM\QueryBuilder;
use DoctrineBatchUtils\BatchProcessing\SimpleBatchIteratorAggregate;
use EMS\CoreBundle\Entity\Revision;

/**
 * @implements \IteratorAggregate<int, Revision>
 */
final class Revisions implements \IteratorAggregate
{
    private QueryBuilder $qb;
    private int $batchSize;

    public function __construct(QueryBuilder $qb, int $batchSize = 50)
    {
        $this->qb = $qb;
        $this->batchSize = $batchSize;
    }

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
     * @return SimpleBatchIteratorAggregate|Revision[]
     */
    public function getIterator(): SimpleBatchIteratorAggregate
    {
        return SimpleBatchIteratorAggregate::fromQuery(
            $this->qb->getQuery(),
            $this->batchSize
        );
    }

    public function batch(callable $batch): void
    {
        foreach ($this->getIterator() as $revision) {
            $batch($revision);
        }
    }
}
