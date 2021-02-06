<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Service\EntityServiceInterface;

final class EntityTable extends TableAbstract
{
    /** @var EntityServiceInterface */
    private $entityService;
    /** @var int */
    private $size;
    /** @var int */
    private $from;
    /**
     * @var mixed|null
     */
    private $context;

    /**
     * @param mixed $context
     */
    public function __construct(EntityServiceInterface $entityService, $context = null, int $from = 0, int $size = 50)
    {
        $this->entityService = $entityService;
        $this->context = $context;
        $this->from = $from;
        $this->size = $size;
    }

    public function isSortable(): bool
    {
        return $this->entityService->isSortable();
    }

    /**
     * @return \IteratorAggregate<string, EntityRow>
     */
    public function getIterator(): iterable
    {
        foreach ($this->entityService->get($this->from, $this->size, $this->context) as $entity) {
            yield \strval($entity->getId()) => new EntityRow($entity);
        }
    }

    public function getAttributeName(): string
    {
        return $this->entityService->getEntityName();
    }

    public function count(): int
    {
        return $this->entityService->count($this->context);
    }
}
