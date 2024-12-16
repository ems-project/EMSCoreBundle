<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Service\QueryServiceInterface;

abstract class AbstractQueryTableType extends AbstractTableType
{
    public function __construct(
        private readonly QueryServiceInterface $queryService,
    ) {
    }

    abstract public function getQueryName(): string;

    public function build(QueryTable $table): void
    {
    }

    public function getQueryService(): QueryServiceInterface
    {
        return $this->queryService;
    }
}
