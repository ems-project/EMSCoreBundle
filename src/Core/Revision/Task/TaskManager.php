<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Service\UserService;
use Psr\Log\LoggerInterface;

final class TaskManager
{
    private TaskRepository $taskRepository;
    private TaskTableService $taskTableService;
    private RevisionRepository $revisionRepository;
    private UserService $userService;
    private LoggerInterface $logger;

    public const TAB_USER = 'user';
    public const TAB_OWNER = 'owner';
    public const TAB_MANAGER = 'manager';

    public function __construct(
        TaskRepository $taskRepository,
        TaskTableService $taskTableService,
        RevisionRepository $revisionRepository,
        UserService $userService,
        LoggerInterface $logger
    ) {
        $this->taskRepository = $taskRepository;
        $this->taskTableService = $taskTableService;
        $this->revisionRepository = $revisionRepository;
        $this->userService = $userService;
        $this->logger = $logger;
    }

    public function getTable(string $ajaxUrl, string $tab): EntityTable
    {
        $taskTableContext = new TaskTableContext($this->userService->getCurrentUser(), $tab);

        $table = new EntityTable($this->taskTableService, $ajaxUrl, $taskTableContext);
        $this->taskTableService->buildTable($table, $taskTableContext);

        return $table;
    }

    public function getCurrentTask(int $revisionId): ?Task
    {
        $revision = $this->revisionRepository->findOneById($revisionId);

        return $revision->hasTaskCurrent() ? $revision->getTaskCurrent() : null;
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

        $revision->addTask($task, $user);
        if ($revision->isTaskCurrent($task)) {
            $task->statusProgress();
        }

        $this->taskRepository->save($task);

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

        if ($revision->isTaskCurrent($task)) {
            $nextPlannedId = $revision->getTaskNextPlannedId();
            $nextPlannedTask = $nextPlannedId ? $this->getTask($nextPlannedId) : null;

            if ($nextPlannedTask) {
                $nextPlannedTask->statusProgress();
                $revision->setTaskCurrent($nextPlannedTask);
                $this->taskRepository->save($nextPlannedTask);
            } else {
                $revision->setTaskCurrent(null);
            }
        } elseif ($revision->isTaskPlanned($task)) {
            $revision->deleteTaskPlanned($task);
        } elseif ($revision->isTaskApproved($task)) {
            $revision->deleteTaskApproved($task);
        }

        $this->revisionRepository->save($revision);
        $this->revisionRepository->unlockRevision($revisionId);

        $this->taskRepository->delete($task);
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

    public function hasDashboard(): bool
    {
        return $this->isTaskUser() || $this->isTaskOwner() || $this->isTaskManager();
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

    public function isTaskManager(): bool
    {
        return $this->userService->isGrantedRole('ROLE_TASK_MANAGER');
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
        $owner = $revision->getOwner();

        $task->changeStatus(Task::STATUS_FINISHED, $user->getUsername(), $comment);
        $task->setAssignee($owner);

        $this->taskRepository->save($task);
        $this->revisionRepository->unlockRevision($revisionId);
    }
}
