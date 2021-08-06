<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

final class TaskTableService implements EntityServiceInterface
{
    private TaskRepository $taskRepository;

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        return $this->taskRepository->findTable($from, $size, $orderField, $orderDirection, $searchValue, $context);
    }

    public function getEntityName(): string
    {
        return 'task';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        return $this->taskRepository->countTable($searchValue, $context);
    }
}
