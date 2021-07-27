<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TaskRepository;
use Psr\Log\LoggerInterface;

final class TaskManager
{
    private TaskRepository $taskRepository;
    private RevisionRepository $revisionRepository;
    private LoggerInterface $logger;

    public function __construct(
        TaskRepository $taskRepository,
        RevisionRepository $revisionRepository,
        LoggerInterface $logger
    ) {
        $this->taskRepository = $taskRepository;
        $this->revisionRepository = $revisionRepository;
        $this->logger = $logger;
    }

    public function create(Task $task, int $revisionId): void
    {
        try {
            $revision = $this->revisionRepository->findOneById($revisionId);
            $this->taskRepository->save($task);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
