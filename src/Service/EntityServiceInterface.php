<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\SubmissionBundle\Entity\EntityInterface;

interface EntityServiceInterface
{
    public function isSortable(): bool;

    /**
     * @param mixed $context
     *
     * @return EntityInterface[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array;

    public function getEntityName(): string;

    /**
     * @param mixed $context
     */
    public function count(string $searchValue = '', $context = null): int;
}
