<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

interface QueryServiceInterface
{
    public function isQuerySortable(): bool;

    /**
     * @param mixed $context
     *
     * @return mixed[]
     */
    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array;

    /**
     * @param mixed $context
     */
    public function countQuery(string $searchValue = '', $context = null): int;
}
