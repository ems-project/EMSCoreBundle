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
    /**
     * @param int<1, max> $batchSize
     */
    public function __construct(private readonly QueryBuilder $qb, private readonly ResultSet $resultSet, private readonly int $batchSize = 50)
    {
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
     * @return iterable|Revision[]
     */
    public function transaction(): iterable
    {
        /** @var Revision[] $results */
        $results = $this->qb->getQuery()->getResult();

        return SimpleBatchIteratorAggregate::fromArrayResult(
            $results,
            $this->qb->getEntityManager(),
            $this->batchSize
        );
    }
}
