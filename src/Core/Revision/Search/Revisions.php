<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Search;

use Doctrine\ORM\QueryBuilder;
use DoctrineBatchUtils\BatchProcessing\SimpleBatchIteratorAggregate;
use Elastica\Document;
use Elastica\ResultSet;
use EMS\CoreBundle\Entity\Revision;

/**
 * @implements \IteratorAggregate<int, Revision>
 */
final class Revisions implements \IteratorAggregate
{
    private QueryBuilder $qb;
    private ResultSet $resultSet;
    private int $batchSize;

    public function __construct(QueryBuilder $qb, ResultSet $resultSet, int $batchSize = 50)
    {
        $this->qb = $qb;
        $this->resultSet = $resultSet;
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

    public function getDocument(Revision $revision): ?Document
    {
        foreach ($this->resultSet->getDocuments() as $document) {
            if ($document instanceof Document && $document->getId() === $revision->giveOuuid()) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @return \ArrayIterator<int, Revision>|Revision[]
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->qb->getQuery()->getResult());
    }

    /**
     * @return SimpleBatchIteratorAggregate|Revision[]
     */
    public function transaction(): SimpleBatchIteratorAggregate
    {
        return SimpleBatchIteratorAggregate::fromArrayResult(
            $this->qb->getQuery()->getResult(),
            $this->qb->getEntityManager(),
            $this->batchSize
        );
    }
}
