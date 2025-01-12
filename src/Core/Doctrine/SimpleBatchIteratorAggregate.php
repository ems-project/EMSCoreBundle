<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Doctrine;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use EMS\CoreBundle\Entity\EntityInterface;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<string|int, EntityInterface>
 */
final readonly class SimpleBatchIteratorAggregate implements \IteratorAggregate
{
    /**
     * @param AbstractQuery<mixed> $query
     */
    public static function fromQuery(AbstractQuery $query, int $batchSize): self
    {
        return new self($query->toIterable(), $query->getEntityManager(), $batchSize);
    }

    /**
     * @param array<string|int, EntityInterface> $results
     *
     * @return \Traversable<string|int, EntityInterface>
     */
    public static function fromArrayResult(array $results, EntityManagerInterface $entityManager, int $batchSize): iterable
    {
        return new self($results, $entityManager, $batchSize);
    }

    /**
     * @return \Traversable<string|int, EntityInterface>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        $iteration = 0;

        $this->entityManager->beginTransaction();

        try {
            foreach ($this->resultSet as $key => $value) {
                ++$iteration;

                if (\is_array($value)) {
                    $firstKey = \key($value);
                    if (null !== $firstKey && \is_object($value[$firstKey]) && $value === [$firstKey => $value[$firstKey]]) {
                        yield $key => $this->reFetchObject($value[$firstKey]);

                        $this->flushAndClearBatch($iteration);
                        continue;
                    }
                }

                if (!\is_object($value)) {
                    yield $key => $value;

                    $this->flushAndClearBatch($iteration);
                    continue;
                }

                yield $key => $this->reFetchObject($value);

                $this->flushAndClearBatch($iteration);
            }
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }

        $this->flushAndClearEntityManager();
        $this->entityManager->commit();
    }

    /**
     * BatchIteratorAggregate constructor (private by design: use a named constructor instead).
     *
     * @param iterable<string|int, EntityInterface> $resultSet
     */
    private function __construct(private iterable $resultSet, private EntityManagerInterface $entityManager, private int $batchSize)
    {
    }

    private function reFetchObject(object $object): EntityInterface
    {
        $className = $object::class;
        $metadata = $this->entityManager->getClassMetadata($className);
        $freshValue = $this->entityManager->find($className, $metadata->getIdentifierValues($object));

        if (!$freshValue instanceof EntityInterface) {
            throw new \UnexpectedValueException(\sprintf('Requested batch item %s, hash %s (of type %s) with the identifier "%s" could not be found', $object::class, \spl_object_hash($object), $metadata->getName(), \json_encode($metadata->getIdentifierValues($object), JSON_THROW_ON_ERROR)));
        }

        return $freshValue;
    }

    private function flushAndClearBatch(int $iteration): void
    {
        if ($iteration % $this->batchSize) {
            return;
        }

        $this->flushAndClearEntityManager();
    }

    private function flushAndClearEntityManager(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
