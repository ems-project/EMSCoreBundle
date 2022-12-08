<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

interface QueryServiceInterface
{
    public function isQuerySortable(): bool;

    /**
     * @return mixed[]
     */
    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array;

    public function countQuery(string $searchValue = '', mixed $context = null): int;
}
