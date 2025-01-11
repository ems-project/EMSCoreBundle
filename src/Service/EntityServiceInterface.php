<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Entity\EntityInterface;

interface EntityServiceInterface
{
    public function isSortable(): bool;

    /**
     * @return EntityInterface[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array;

    public function getEntityName(): string;

    /**
     * @return string[]
     */
    public function getAliasesName(): array;

    public function count(string $searchValue = '', mixed $context = null): int;

    public function getByItemName(string $name): ?EntityInterface;

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface;

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface;

    public function deleteByItemName(string $name): string;
}
