<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\EntityInterface;

interface EntityServiceInterface
{
    public function isSortable(): bool;

    /**
     * @return EntityInterface[]
     */
    public function get(int $from, int $size): array;

    public function getEntityName(): string;

    /**
     * @param mixed $context
     */
    public function count($context = null): int;
}
