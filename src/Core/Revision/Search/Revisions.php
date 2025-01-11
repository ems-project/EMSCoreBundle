<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Search;

use Doctrine\ORM\QueryBuilder;
use Elastica\Document;
use Elastica\ResultSet;
use EMS\CoreBundle\Core\Doctrine\SimpleBatchIteratorAggregate;
use EMS\CoreBundle\Entity\Revision;

/**
 * @implements \IteratorAggregate<int, Revision>
 */
final readonly class Revisions implements \IteratorAggregate
{
    /**
     * @param int<1, max> $batchSize
     */
    public function __construct(private QueryBuilder $qb, private ResultSet $resultSet, private int $batchSize = 50)
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
    #[\Override]
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

        /** @var \Traversable<string|int, Revision> $iterator */
        $iterator = SimpleBatchIteratorAggregate::fromArrayResult(
            $results,
            $this->qb->getEntityManager(),
            $this->batchSize
        );

        return $iterator;
    }
}
