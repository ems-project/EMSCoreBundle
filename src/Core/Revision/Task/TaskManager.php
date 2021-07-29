<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TaskRepository;
use http\Exception\RuntimeException;
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

    public function getTask(string $taskId): Task
    {
        $task = $this->taskRepository->findOneBy(['id' => $taskId]);

        if (!$task instanceof Task) {
            throw new RuntimeException(sprintf('Task with id "%s" not found', $taskId));
        }

        return $task;
    }

    public function getTaskCollection(int $revisionId): TaskCollection
    {
        $revision = $this->revisionRepository->findOneById($revisionId);

        $results = $this->taskRepository->getTasks($revision);

        return new TaskCollection($revision, $results);
    }

    public function create(Task $task, int $revisionId): void
    {
        try {
            $now = new \DateTimeImmutable('now');
            $this->revisionRepository->lockRevision($revisionId, 'SYSTEM_TASK', $now->modify('+1min'));

            $revision = $this->revisionRepository->findOneById($revisionId);

            $this->taskRepository->save($task);
            $revision->getTasks()->add($task);

            $this->revisionRepository->save($revision);
            $this->revisionRepository->unlockRevision($revisionId);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    public function update(Task $task, int $revisionId): void
    {
        try {
            $now = new \DateTimeImmutable('now');
            $this->revisionRepository->lockRevision($revisionId, 'SYSTEM_TASK', $now->modify('+1min'));

            $revision = $this->revisionRepository->findOneById($revisionId);

            $this->taskRepository->save($task);

            $this->revisionRepository->save($revision);
            $this->revisionRepository->unlockRevision($revisionId);

        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
