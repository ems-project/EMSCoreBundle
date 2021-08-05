<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Service\UserService;
use Psr\Log\LoggerInterface;

final class TaskManager
{
    private TaskRepository $taskRepository;
    private RevisionRepository $revisionRepository;
    private UserService $userService;
    private LoggerInterface $logger;

    public function __construct(
        TaskRepository $taskRepository,
        RevisionRepository $revisionRepository,
        UserService $userService,
        LoggerInterface $logger
    ) {
        $this->taskRepository = $taskRepository;
        $this->revisionRepository = $revisionRepository;
        $this->userService = $userService;
        $this->logger = $logger;
    }

    public function getCurrentTask(int $revisionId): ?Task
    {
        $revision = $this->revisionRepository->findOneById($revisionId);

        if (!$revision->getTasks()->hasCurrentId()) {
            return null;
        }

        return $this->getTask($revision->getTasks()->getCurrentId());
    }

    public function getTask(string $taskId): Task
    {
        $task = $this->taskRepository->findOneBy(['id' => $taskId]);

        if (!$task instanceof Task) {
            throw new \RuntimeException(\sprintf('Task with id "%s" not found', $taskId));
        }

        return $task;
    }

    public function getTaskCollection(int $revisionId): TaskCollection
    {
        $revision = $this->revisionRepository->findOneById($revisionId);

        $results = $this->taskRepository->getTasks($revision);

        return new TaskCollection($revision, $results);
    }

    public function create(TaskDTO $taskDTO, int $revisionId): Task
    {
        $user = $this->userService->getCurrentUser();

        $now = new \DateTimeImmutable('now');
        $this->revisionRepository->lockRevision($revisionId, 'SYSTEM_TASK', $now->modify('+1min'));

        $task = Task::createFromDTO($taskDTO, $user->getUsername());
        $revision = $this->revisionRepository->findOneById($revisionId);

        $this->taskRepository->save($task);

        $revision->getTasks()->add($task, $user);
        $this->revisionRepository->save($revision);
        $this->revisionRepository->unlockRevision($revisionId);

        return $task;
    }

    public function update(Task $task, TaskDTO $taskDTO, int $revisionId): void
    {
        $now = new \DateTimeImmutable('now');
        $this->revisionRepository->lockRevision($revisionId, 'SYSTEM_TASK', $now->modify('+1min'));

        $task->updateFromDTO($taskDTO);
        $this->taskRepository->save($task);

        $this->revisionRepository->unlockRevision($revisionId);
    }

    public function delete(Task $task, int $revisionId): void
    {
        $now = new \DateTimeImmutable('now');
        $this->revisionRepository->lockRevision($revisionId, 'SYSTEM_TASK', $now->modify('+1min'));

        $revision = $this->revisionRepository->findOneById($revisionId);

        if (!$revision->getTasks()->delete($task)) {
            throw new \RuntimeException(\sprintf('Task with id "%s" not attached to revision', $task->getId()));
        }

        $this->revisionRepository->save($revision);
        $this->revisionRepository->unlockRevision($revisionId);

        $this->taskRepository->delete($task);
    }

    public function canRequestValidation(Task $task): bool
    {
        $user = $this->userService->getCurrentUser();

        return $task->getAssignee() === $user->getUsername();
    }

    public function requestValidation(Task $task, int $revisionId, string $comment): void
    {
        $user = $this->userService->getCurrentUser();

        $now = new \DateTimeImmutable('now');
        $this->revisionRepository->lockRevision($revisionId, $user->getUsername(), $now->modify('+10min'));

        $this->revisionRepository->clear();
        $revision = $this->revisionRepository->findOneById($revisionId);
        $owner = $revision->getTasks()->getOwner();

        $task->changeStatus(Task::STATUS_FINISHED, $user->getUsername(), $comment);
        $task->setAssignee($owner);

        $this->taskRepository->save($task);
        $this->revisionRepository->unlockRevision($revisionId);
    }
}
