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

final class TaskManager
{
    private TaskRepository $taskRepository;
    private TaskTableService $taskTableService;
    private RevisionRepository $revisionRepository;
    private DataService $dataService;
    private UserService $userService;
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
        LoggerInterface $logger
    ) {
        $this->taskRepository = $taskRepository;
        $this->taskTableService = $taskTableService;
        $this->revisionRepository = $revisionRepository;
        $this->dataService = $dataService;
        $this->userService = $userService;
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

    public function getTable(string $ajaxUrl, string $tab): EntityTable
    {
        $taskTableContext = new TaskTableContext($this->userService->getCurrentUser(), $tab);

        $table = new EntityTable($this->taskTableService, $ajaxUrl, $taskTableContext);
        $this->taskTableService->buildTable($table, $taskTableContext);

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
            $task = Task::createFromDTO($taskDTO, $user);

            $revision->addTask($task, $user->getUsername());
            if ($revision->isTaskCurrent($task)) {
                $task->statusProgress($user->getUsername());
            }

            $this->taskRepository->save($task);
            $this->revisionRepository->save($revision);

            return $task;
        });

        return $transaction($revisionId);
    }

    public function taskDelete(Task $task, int $revisionId): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task) {
            if ($revision->isTaskCurrent($task)) {
                $this->setNextPlanned($revision);
            } elseif ($revision->isTaskPlanned($task)) {
                $revision->deleteTaskPlanned($task);
            } elseif ($revision->isTaskApproved($task)) {
                $revision->deleteTaskApproved($task);
            }

            $this->revisionRepository->save($revision);
            $this->taskRepository->delete($task);
        });
        $transaction($revisionId);
    }

    public function taskUpdate(Task $task, TaskDTO $taskDTO, int $revisionId): void
    {
        $transaction = $this->revisionTransaction(function () use ($task, $taskDTO) {
            $task->updateFromDTO($taskDTO);
            $this->taskRepository->save($task);
        });
        $transaction($revisionId);
    }

    public function taskValidate(Revision $revision, bool $approve, ?string $comment): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($approve, $comment) {
            $user = $this->userService->getCurrentUser();
            $task = $revision->getTaskCurrent();

            if ($approve) {
                $task->changeStatus(Task::STATUS_APPROVED, $user->getUsername(), $comment);
                $revision->addTask($task, $revision->getOwner());
                $this->setNextPlanned($revision);
                $this->revisionRepository->save($revision);
            } else {
                $task->changeStatus(Task::STATUS_REJECTED, $user->getUsername(), $comment);
            }

            $this->taskRepository->save($task);
        });
        $transaction($revision->getId());
    }

    public function taskValidateRequest(Task $task, int $revisionId, string $comment): void
    {
        $transaction = $this->revisionTransaction(function () use ($task, $comment) {
            $user = $this->userService->getCurrentUser();
            $task->changeStatus(Task::STATUS_COMPLETED, $user->getUsername(), $comment);
            $this->taskRepository->save($task);
        });
        $transaction($revisionId);
    }

    /**
     * @param string[] $orderedTaskIds
     */
    public function tasksReorder(int $revisionId, array $orderedTaskIds): void
    {
        if (count($orderedTaskIds) === 0) {
            return;
        }

        $transaction = $this->revisionTransaction(function (Revision $revision) use ($orderedTaskIds) {
            $user = $this->userService->getCurrentUser();

            $orderCurrentTaskId = \array_shift($orderedTaskIds);
            $oldCurrentTask = $revision->getTaskCurrent();
            $orderTaskCurrent = $this->getTask($orderCurrentTaskId);

            if ($revision->taskCurrentReplace($orderTaskCurrent, $user->getUsername())) {
                $this->taskRepository->save($oldCurrentTask);
                $this->taskRepository->save($orderTaskCurrent);
            }

            $revision->setTaskPlanned($this->taskRepository->findTasksByIds($orderedTaskIds));

            $this->revisionRepository->save($revision);
        });
        $transaction($revisionId);
    }

    private function setNextPlanned(Revision $revision): void
    {
        $nextPlannedId = $revision->getTaskNextPlannedId();
        $nextPlannedTask = $nextPlannedId ? $this->getTask($nextPlannedId) : null;

        if ($nextPlannedTask) {
            $nextPlannedTask->statusProgress($revision->getOwner());
            $revision->setTaskCurrent($nextPlannedTask);
            $this->taskRepository->save($nextPlannedTask);
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
            }
        };
    }
}
