<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Core\Revision\Task\Table\TaskTableContext;
use EMS\CoreBundle\Core\Revision\Task\Table\TaskTableFilters;
use EMS\CoreBundle\Core\Revision\Task\Table\TaskTableService;
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
    public const TAB_USER = 'user';
    public const TAB_REQUESTER = 'requester';
    public const TAB_MANAGER = 'manager';

    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskTableService $taskTableService,
        private readonly RevisionRepository $revisionRepository,
        private readonly DataService $dataService,
        private readonly UserService $userService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly string $templateNamespace)
    {
    }

    public function countApprovedTasks(Revision $revision): int
    {
        return $this->taskRepository->countApproved($revision);
    }

    /**
     * @return string[]
     */
    public function getDashboardTabs(): array
    {
        return \array_filter([
            self::TAB_USER,
            self::TAB_REQUESTER,
            $this->isTaskManager() ? self::TAB_MANAGER : null,
        ]);
    }

    public function getRevision(int $revisionId): Revision
    {
        return $this->revisionRepository->findOneById($revisionId);
    }

    public function getTable(string $ajaxUrl, string $tab, TaskTableFilters $filters, bool $export): EntityTable
    {
        $taskTableContext = new TaskTableContext($this->userService->getCurrentUser(), $tab, $filters);
        $taskTableContext->showVersionTagColumn = $this->taskRepository->hasVersionedContentType();

        $table = new EntityTable($this->templateNamespace, $this->taskTableService, $ajaxUrl, $taskTableContext);

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

    public function isTaskAssignee(Task $task): bool
    {
        $user = $this->userService->getCurrentUser();

        return $task->getAssignee() === $user->getUsername();
    }

    public function isTaskRequester(Task $task): bool
    {
        $user = $this->userService->getCurrentUser();

        return $task->getCreatedBy() === $user->getUsername();
    }

    public function isTaskManager(): bool
    {
        return $this->userService->isGrantedRole('ROLE_TASK_MANAGER');
    }

    public function taskCreate(TaskDTO $taskDTO, int $revisionId): Task
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($taskDTO) {
            $user = $this->userService->getCurrentUser();
            $task = $this->taskCreateFromRevision($taskDTO, $revision, $user->getUsername());
            $this->revisionRepository->save($revision);

            return $task;
        });

        return $transaction($revisionId);
    }

    public function taskCreateFromRevision(TaskDTO $taskDTO, Revision $revision, string $username): Task
    {
        $task = Task::createFromDTO($taskDTO, $username);
        $revision->addTask($task);

        $this->dispatchEvent($this->createTaskEvent($task, $revision, $username), TaskEvent::CREATE);
        if ($revision->isTaskCurrent($task)) {
            $this->dispatchEvent($this->createTaskEvent($task, $revision, $username), TaskEvent::PROGRESS);
        }

        return $task;
    }

    public function taskDelete(Task $task, int $revisionId, ?string $description = null): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $description) {
            $currentDescription = $task->hasDescription() ? $task->getDescription() : null;
            if ($description !== $currentDescription) {
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
            $this->dispatchEvent($this->createTaskEvent($task, $revision), TaskEvent::DELETE);
        });
        $transaction($revisionId);
    }

    public function taskUpdate(Task $task, TaskDTO $taskDTO, int $revisionId): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $taskDTO) {
            $task->updateFromDTO($taskDTO);
            $event = $this->createTaskEvent($task, $revision);
            $event->changeSet = $this->taskRepository->update($task);
            $this->dispatchEvent($event, TaskEvent::UPDATE);
        });
        $transaction($revisionId);
    }

    public function taskValidate(Revision $revision, bool $approve, ?string $comment): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($approve, $comment) {
            $task = $revision->getTaskCurrent();
            $event = $this->createTaskEvent($task, $revision);
            $event->comment = $comment;

            if ($approve) {
                $this->dispatchEvent($event, TaskEvent::APPROVED);
                $revision->addTask($task);
                $this->setNextPlanned($revision);
            } else {
                $revision->updateModified();
                $this->dispatchEvent($event, TaskEvent::REJECTED);
            }

            $this->revisionRepository->save($revision);
        });
        $transaction($revision->getId());
    }

    public function taskValidateRequest(Task $task, int $revisionId, string $comment): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $comment) {
            $event = $this->createTaskEvent($task, $revision);
            $event->comment = $comment;
            $this->dispatchEvent($event, TaskEvent::COMPLETED);
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

            if ($revision->taskCurrentReplace($orderTaskCurrent)) {
                $this->dispatchEvent($this->createTaskEvent($oldCurrentTask, $revision), TaskEvent::PLANNED);
                $this->dispatchEvent($this->createTaskEvent($orderTaskCurrent, $revision), TaskEvent::PROGRESS);
            }

            $revision->setTaskPlanned($this->taskRepository->findTasksByIds($orderedTaskIds));

            $this->revisionRepository->save($revision);
        });
        $transaction($revisionId);
    }

    private function dispatchEvent(TaskEvent $event, string $eventName): void
    {
        $this->eventDispatcher->dispatch($event, $eventName);
    }

    private function createTaskEvent(Task $task, Revision $revision, ?string $username = null): TaskEvent
    {
        $username = $username ?: $this->userService->getCurrentUser()->getUsername();

        return new TaskEvent($task, $revision, $username);
    }

    private function setNextPlanned(Revision $revision): void
    {
        $nextPlannedId = $revision->getTaskNextPlannedId();
        $nextPlannedTask = $nextPlannedId ? $this->getTask($nextPlannedId) : null;

        if ($nextPlannedTask) {
            $revision->setTaskCurrent($nextPlannedTask);
            $this->dispatchEvent($this->createTaskEvent($nextPlannedTask, $revision), TaskEvent::PROGRESS);
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

                $result = $execute($revision);

                $this->dataService->unlockRevision($revision);

                return $result;
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
                throw $e;
            }
        };
    }
}
