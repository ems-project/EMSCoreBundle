<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\EntityServiceInterface;

final class EntityTable extends TableAbstract
{
    private EntityServiceInterface $entityService;
    private int $size;
    private int $from;
    private ?string $orderField = null;
    private string $orderDirection = 'asc';
    private string $searchValue = '';
    private ?string $ajaxUrl = null;
    private bool $loadAll;
    private ?int $count = null;
    private ?int $totalCount = null;
    /**
     * @var mixed|null
     */
    private $context;

    /**
     * @param mixed $context
     */
    public function __construct(EntityServiceInterface $entityService, string $ajaxUrl, $context = null, int $loadAllMaxRow = 400)
    {
        $this->entityService = $entityService;
        $this->context = $context;

        if ($this->count() > $loadAllMaxRow) {
            $this->ajaxUrl = $ajaxUrl;
            $this->from = 0;
            $this->size = 0;
            $this->loadAll = false;
        } else {
            $this->from = 0;
            $this->size = $loadAllMaxRow;
            $this->loadAll = true;
        }
    }

    public function resetIterator(DataTableRequest $dataTableRequest): void
    {
        $this->from = $dataTableRequest->getFrom();
        $this->size = $dataTableRequest->getSize();
        $this->orderField = $dataTableRequest->getOrderField();
        $this->orderDirection = $dataTableRequest->getOrderDirection();
        $this->searchValue = $dataTableRequest->getSearchValue();
        $this->totalCount = null;
        $this->count = null;
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
        foreach ($this->entityService->get($this->from, $this->size, $this->orderField, $this->orderDirection, $this->searchValue, $this->context) as $entity) {
            yield \strval($entity->getId()) => new EntityRow($entity);
        }
    }

    public function getAttributeName(): string
    {
        return $this->entityService->getEntityName();
    }

    public function totalCount(): int
    {
        if (null === $this->totalCount) {
            $this->totalCount = $this->entityService->count('', $this->context);
        }

        return $this->totalCount;
    }

    public function count(): int
    {
        if (null === $this->count) {
            $this->count = $this->entityService->count($this->searchValue, $this->context);
        }

        return $this->count;
    }

    public function getAjaxUrl(): ?string
    {
        return $this->ajaxUrl;
    }

    public function supportsTableActions(): bool
    {
        if (!$this->loadAll) {
            return false;
        }
        if ($this->totalCount() <= 1) {
            return false;
        }
        foreach ($this->getTableActions() as $action) {
            return true;
        }

        return false;
    }
}
