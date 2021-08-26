<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TaskManager
{
    private TaskRepository $taskRepository;
    private TaskTableService $taskTableService;
    private RevisionRepository $revisionRepository;
    private DataService $dataService;
    private UserService $userService;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;

    public const TAB_USER = 'user';
    public const TAB_OWNER = 'owner';
    public const TAB_MANAGER = 'manager';

    public function __construct(
        TaskRepository $taskRepository,
        TaskTableService $taskTableService,
        RevisionRepository $revisionRepository,
        DataService $dataService,
        UserService $userService,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->taskRepository = $taskRepository;
        $this->taskTableService = $taskTableService;
        $this->revisionRepository = $revisionRepository;
        $this->dataService = $dataService;
        $this->userService = $userService;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function countApprovedTasks(Revision $revision): int
    {
        return $this->taskRepository->countApproved($revision);
    }

    public function changeOwner(Revision $revision, string $newOwner): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($newOwner) {
            $revision->setOwner($newOwner);
            $this->revisionRepository->save($revision);
        });

        $transaction($revision->getId());
    }

    /**
     * @return string[]
     */
    public function getDashboardTabs(): array
    {
        return \array_filter([
            self::TAB_USER,
            ($this->isTaskOwner() ? self::TAB_OWNER : null),
            ($this->isTaskManager() ? self::TAB_MANAGER : null),
        ]);
    }

    public function getRevision(int $revisionId): Revision
    {
        return $this->revisionRepository->findOneById($revisionId);
    }

    public function getTable(string $ajaxUrl, string $tab, bool $export): EntityTable
    {
        $taskTableContext = new TaskTableContext($this->userService->getCurrentUser(), $tab);

        $table = new EntityTable($this->taskTableService, $ajaxUrl, $taskTableContext);

        if ($export) {
            $this->taskTableService->buildTableExport($table, $taskTableContext);
        } else {
            $this->taskTableService->buildTable($table, $taskTableContext);
        }

        return $table;
    }

    public function getTask(string $taskId): Task
    {
        $task = $this->taskRepository->findOneBy(['id' => $taskId]);

        if (!$task instanceof Task) {
            throw new \RuntimeException(\sprintf('Task with id "%s" not found', $taskId));
        }

        return $task;
    }

    public function getTasks(int $revisionId): TaskCollection
    {
        $revision = $this->revisionRepository->findOneById($revisionId);
        $tasks = new TaskCollection($revision);

        if ($revision->hasTaskCurrent()) {
            $tasks->addTask($this->taskRepository->findTaskById($revision->getTaskCurrent()->getId()));
        }
        if ($revision->hasTaskPlannedIds()) {
            $tasks->addTasks($this->taskRepository->findTasksByIds($revision->getTaskPlannedIds()));
        }

        return $tasks;
    }

    public function getTasksApproved(int $revisionId): TaskCollection
    {
        $revision = $this->revisionRepository->findOneById($revisionId);
        $tasks = new TaskCollection($revision);

        if ($revision->hasTaskApprovedIds()) {
            $tasks->addTasks($this->taskRepository->findTasksByIds($revision->getTaskApprovedIds()));
        }

        return $tasks;
    }

    public function getTaskCurrent(int $revisionId): ?Task
    {
        $revision = $this->revisionRepository->findOneById($revisionId);

        return $revision->hasTaskCurrent() ? $revision->getTaskCurrent() : null;
    }

    public function hasDashboard(): bool
    {
        return $this->isTaskUser() || $this->isTaskOwner() || $this->isTaskManager();
    }

    public function isTaskAssignee(Task $task): bool
    {
        $user = $this->userService->getCurrentUser();

        return $task->getAssignee() === $user->getUsername();
    }

    public function isTaskUser(): bool
    {
        $user = $this->userService->getCurrentUser();

        return $this->taskRepository->countForUser($user) > 0;
    }

    public function isTaskOwner(): bool
    {
        $user = $this->userService->getCurrentUser();

        return $this->taskRepository->countForOwner($user) > 0;
    }

    public function isTaskOwnerRevision(Revision $revision): bool
    {
        $user = $this->userService->getCurrentUser();

        return $revision->hasOwner() && $revision->getOwner() === $user->getUsername();
    }

    public function isTaskManager(): bool
    {
        return $this->userService->isGrantedRole('ROLE_TASK_MANAGER');
    }

    public function taskCreate(TaskDTO $taskDTO, int $revisionId): Task
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($taskDTO) {
            $user = $this->userService->getCurrentUser();
            $task = Task::createFromDTO($taskDTO);
            $revision->addTask($task, $user->getUsername());

            $this->dispatchEvent($task, $revision, TaskEvent::CREATE);
            if ($revision->isTaskCurrent($task)) {
                $this->dispatchEvent($task, $revision, TaskEvent::PROGRESS);
            }

            $this->revisionRepository->save($revision);

            return $task;
        });

        return $transaction($revisionId);
    }

    public function taskDelete(Task $task, int $revisionId, ?string $description = null): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $description) {
            if ($description !== $task->getDescription()) {
                $task->setDescription($description);
                $this->taskRepository->save($task);
            }

            if ($revision->isTaskCurrent($task)) {
                $this->setNextPlanned($revision);
            } elseif ($revision->isTaskPlanned($task)) {
                $revision->deleteTaskPlanned($task);
            } elseif ($revision->isTaskApproved($task)) {
                $revision->deleteTaskApproved($task);
            }

            $this->revisionRepository->save($revision);
            $this->taskRepository->delete($task);

            $this->dispatchEvent($task, $revision, TaskEvent::DELETE);
        });
        $transaction($revisionId);
    }

    public function taskUpdate(Task $task, TaskDTO $taskDTO, int $revisionId): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $taskDTO) {
            $task->updateFromDTO($taskDTO);
            $changeSet = $this->taskRepository->update($task);
            $this->dispatchEvent($task, $revision, TaskEvent::UPDATE, null, $changeSet);
        });
        $transaction($revisionId);
    }

    public function taskValidate(Revision $revision, bool $approve, ?string $comment): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($approve, $comment) {
            $task = $revision->getTaskCurrent();

            if ($approve) {
                $this->dispatchEvent($task, $revision, TaskEvent::APPROVED, $comment);
                $revision->addTask($task, $revision->getOwner());
                $this->setNextPlanned($revision);
            } else {
                $revision->updateModified();
                $this->dispatchEvent($task, $revision, TaskEvent::REJECTED, $comment);
            }

            $this->revisionRepository->save($revision);
        });
        $transaction($revision->getId());
    }

    public function taskValidateRequest(Task $task, int $revisionId, string $comment): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $comment) {
            $this->dispatchEvent($task, $revision, TaskEvent::COMPLETED, $comment);
        });
        $transaction($revisionId);
    }

    /**
     * @param string[] $orderedTaskIds
     */
    public function tasksReorder(int $revisionId, array $orderedTaskIds): void
    {
        if (0 === \count($orderedTaskIds)) {
            return;
        }

        $transaction = $this->revisionTransaction(function (Revision $revision) use ($orderedTaskIds) {
            $user = $this->userService->getCurrentUser();

            $orderCurrentTaskId = \array_shift($orderedTaskIds);
            $oldCurrentTask = $revision->getTaskCurrent();
            $orderTaskCurrent = $this->getTask($orderCurrentTaskId);

            if ($revision->taskCurrentReplace($orderTaskCurrent, $user->getUsername())) {
                $this->dispatchEvent($oldCurrentTask, $revision, TaskEvent::PLANNED);
                $this->dispatchEvent($orderTaskCurrent, $revision, TaskEvent::PROGRESS);
            }

            $revision->setTaskPlanned($this->taskRepository->findTasksByIds($orderedTaskIds));

            $this->revisionRepository->save($revision);
        });
        $transaction($revisionId);
    }

    /**
     * @param array<mixed> $changeSet
     */
    private function dispatchEvent(Task $task, Revision $revision, string $eventName, ?string $comment = null, array $changeSet = []): void
    {
        $user = $this->userService->getCurrentUser();
        $event = new TaskEvent($task, $revision, $user);
        $event->setComment($comment);
        $event->setChangeSet($changeSet);
        $this->eventDispatcher->dispatch($event, $eventName); /* @phpstan-ignore-line */
    }

    private function setNextPlanned(Revision $revision): void
    {
        $nextPlannedId = $revision->getTaskNextPlannedId();
        $nextPlannedTask = $nextPlannedId ? $this->getTask($nextPlannedId) : null;

        if ($nextPlannedTask) {
            $revision->setTaskCurrent($nextPlannedTask);
            $this->dispatchEvent($nextPlannedTask, $revision, TaskEvent::PROGRESS);
        } else {
            $revision->setTaskCurrent(null);
            $this->revisionRepository->save($revision);
        }
    }

    private function revisionTransaction(callable $execute): callable
    {
        return function (int $revisionId) use ($execute) {
            try {
                $revision = $this->revisionRepository->findOneById($revisionId);
                $this->dataService->lockRevision($revision);

                $this->revisionRepository->clear();
                $revisionLocked = $this->revisionRepository->findOneById($revisionId);

                $result = $execute($revisionLocked);

                $this->dataService->unlockRevision($revisionLocked);

                return $result;
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
                throw $e;
            }
        };
    }
}
