<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class TaskManager
{
    public function __construct(
        private TaskRepository $taskRepository,
        private RevisionRepository $revisionRepository,
        private DataService $dataService,
        private UserService $userService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function countApprovedTasks(Revision $revision): int
    {
        return $this->taskRepository->countApproved($revision);
    }

    public function getTask(string $taskId, Revision $revision): Task
    {
        $task = $this->taskRepository->findOneBy(['id' => $taskId]);

        if (!$task instanceof Task) {
            throw new \RuntimeException(\sprintf('Task with id "%s" not found', $taskId));
        }

        if (!$revision->hasTask($task)) {
            throw new \RuntimeException('Revision has no tasks');
        }

        return $task;
    }

    /**
     * @return Revision[]
     */
    public function getRevisionsWithCurrentTask(?\DateTimeImmutable $deadlineStart = null, ?\DateTimeImmutable $deadlineEnd = null): array
    {
        $statuses = [TaskStatus::PROGRESS, TaskStatus::REJECTED, TaskStatus::COMPLETED];

        return $this->revisionRepository->findAllWithCurrentTask($deadlineStart, $deadlineEnd, ...$statuses);
    }

    /**
     * @return array<string, User>
     */
    public function getTaskManagers(): array
    {
        $users = $this->userService->findUsersWithRoles([Roles::ROLE_TASK_MANAGER]);

        return \array_reduce($users, static function ($carry, User $user) {
            $carry[$user->getUsername()] = $user;

            return $carry;
        }, []);
    }

    public function getTasksPlanned(Revision $revision): TaskCollection
    {
        if ($revision->hasTaskPlannedIds()) {
            $tasksPlanned = $this->taskRepository->findTasksByIds($revision->getTaskPlannedIds());
        }

        return new TaskCollection(revision: $revision, tasks: $tasksPlanned ?? []);
    }

    public function getTasksApproved(Revision $revision): TaskCollection
    {
        if ($revision->hasTaskApprovedIds()) {
            $tasksApproved = $this->taskRepository->findTasksByIds($revision->getTaskApprovedIds());
        }

        $taskCollection = new TaskCollection(revision: $revision, tasks: $tasksApproved ?? []);

        return $taskCollection->sort(fn (Task $a, Task $b) => $b->getModified() <=> $a->getModified());
    }

    public function getTaskCurrent(Revision $revision): ?Task
    {
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

    public function isTaskManager(?UserInterface $user = null): bool
    {
        if ($user) {
            return $user->hasRole(Roles::ROLE_TASK_MANAGER);
        }

        return $this->userService->isGrantedRole(Roles::ROLE_TASK_MANAGER);
    }

    public function taskCreate(TaskDTO $taskDTO, Revision $revision): Task
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($taskDTO) {
            $user = $this->userService->getCurrentUser();
            $task = $this->taskCreateFromRevision($taskDTO, $revision, $user->getUsername());
            $this->revisionRepository->save($revision);

            return $task;
        });

        return $transaction($revision);
    }

    public function taskCreateFromRevision(TaskDTO $taskDTO, Revision $revision, string $username): Task
    {
        $task = Task::createFromDTO($taskDTO, $revision, $username);
        $revision->addTask($task);

        $this->dispatchEvent($this->createTaskEvent($task, $revision, $username), TaskEvent::CREATE);
        if ($revision->isTaskCurrent($task)) {
            $this->dispatchEvent($this->createTaskEvent($task, $revision, $username), TaskEvent::PROGRESS);
        }

        return $task;
    }

    public function taskDelete(Task $task, Revision $revision, ?string $comment = null): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $comment) {
            if ($revision->isTaskCurrent($task)) {
                $this->setNextPlanned($revision);
            } elseif ($revision->isTaskPlanned($task)) {
                $revision->deleteTaskPlanned($task);
            } elseif ($revision->isTaskApproved($task)) {
                $revision->deleteTaskApproved($task);
            }

            $this->revisionRepository->save($revision);
            $this->taskRepository->delete($task);

            $event = $this->createTaskEvent($task, $revision);
            $event->setComment($comment);
            $this->dispatchEvent($event, TaskEvent::DELETE);
        });
        $transaction($revision);
    }

    public function tasksDeleteByRevision(Revision $revision): void
    {
        $tasks = $this->taskRepository->findBy(['revisionOuuid' => $revision->getOuuid()]);

        foreach ($tasks as $task) {
            $this->taskRepository->delete($task);
        }
    }

    public function taskUpdate(Task $task, TaskDTO $taskDTO, Revision $revision): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $taskDTO) {
            $task->updateFromDTO($taskDTO);
            $event = $this->createTaskEvent($task, $revision);
            $event->changeSet = $this->taskRepository->update($task);
            $this->dispatchEvent($event, TaskEvent::UPDATE);
        });
        $transaction($revision);
    }

    public function taskSave(Task $task): void
    {
        $this->taskRepository->save($task);
    }

    public function taskValidate(Revision $revision, bool $approve, ?string $comment): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($approve, $comment) {
            $task = $revision->getTaskCurrent();
            $event = $this->createTaskEvent($task, $revision);
            $event->setComment($comment);

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
        $transaction($revision);
    }

    public function taskValidateRequest(Task $task, Revision $revision, ?string $comment = null): void
    {
        $transaction = $this->revisionTransaction(function (Revision $revision) use ($task, $comment) {
            $event = $this->createTaskEvent($task, $revision);
            $event->setComment($comment);

            if ($task->isRequester($this->userService->getCurrentUser())) {
                $this->dispatchEvent($event, TaskEvent::APPROVED);
                $revision->addTask($task);
                $this->setNextPlanned($revision);
            } else {
                $this->dispatchEvent($event, TaskEvent::COMPLETED);
            }
        });
        $transaction($revision);
    }

    /**
     * @param string[] $orderedTaskIds
     */
    public function tasksReorder(Revision $revision, array $orderedTaskIds): void
    {
        if (0 === \count($orderedTaskIds)) {
            return;
        }

        $transaction = $this->revisionTransaction(function (Revision $revision) use ($orderedTaskIds) {
            $revision->setTaskPlanned($this->taskRepository->findTasksByIds($orderedTaskIds));
            $this->revisionRepository->save($revision);
        });
        $transaction($revision);
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
        $nextPlannedTask = $nextPlannedId ? $this->getTask($nextPlannedId, $revision) : null;

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
        return function (Revision $revision) use ($execute) {
            try {
                if (!$revision->tasksEnabled()) {
                    throw new \RuntimeException(\sprintf('Tasks not enabled for revision %d', $revision->getId()));
                }

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
