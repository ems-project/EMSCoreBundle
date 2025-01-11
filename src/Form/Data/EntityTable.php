<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\EntityServiceInterface;

use function Symfony\Component\String\u;

final class EntityTable extends TableAbstract
{
    private bool $loadAll;
    private ?int $count = null;
    private ?int $totalCount = null;
    private bool $massAction = true;

    /**
     * @param mixed $context
     */
    public function __construct(
        private readonly string $templateNamespace,
        private readonly EntityServiceInterface $entityService,
        string $ajaxUrl,
        private $context = null,
        int $loadAllMaxRow = 400)
    {
        if ($this->count() > $loadAllMaxRow) {
            parent::__construct($ajaxUrl, 0, 0);
            $this->loadAll = false;
        } else {
            parent::__construct(null, 0, $loadAllMaxRow);
            $this->loadAll = true;
        }
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    public function setMassAction(bool $massAction): void
    {
        $this->massAction = $massAction;
    }

    #[\Override]
    public function resetIterator(DataTableRequest $dataTableRequest): void
    {
        parent::resetIterator($dataTableRequest);
        $this->totalCount = null;
        $this->count = null;
    }

    #[\Override]
    public function isSortable(): bool
    {
        return $this->entityService->isSortable();
    }

    /**
     * @return \Traversable<string, EntityRow>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        foreach ($this->entityService->get($this->getFrom(), $this->getSize(), $this->getOrderField(), $this->getOrderDirection(), $this->getSearchValue(), $this->context) as $entity) {
            yield \strval($entity->getId()) => new EntityRow($entity);
        }
    }

    #[\Override]
    public function getAttributeName(): string
    {
        return u($this->entityService->getEntityName())->camel()->toString();
    }

    #[\Override]
    public function totalCount(): int
    {
        if (null === $this->totalCount) {
            $this->totalCount = $this->entityService->count('', $this->context);
        }

        return $this->totalCount;
    }

    #[\Override]
    public function count(): int
    {
        if (null === $this->count) {
            $this->count = $this->entityService->count($this->getSearchValue(), $this->context);
        }

        return $this->count;
    }

    #[\Override]
    public function supportsTableActions(): bool
    {
        if (!$this->loadAll) {
            return false;
        }
        $min = $this->massAction ? 0 : 1;

        if ($this->totalCount() <= $min) {
            return false;
        }
        foreach ($this->getTableActions() as $action) {
            return true;
        }

        return false;
    }

    #[\Override]
    public function getRowTemplate(): string
    {
        return \sprintf("{%%- use '@$this->templateNamespace/datatable/row.json.twig' -%%}{{ block('emsco_datatable_row') }}");
    }
}
