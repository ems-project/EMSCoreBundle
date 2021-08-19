<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Repository\TaskRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TaskEventSubscriber implements EventSubscriberInterface
{
    private TaskRepository $taskRepository;

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskEvent::CREATE => 'onTaskCreate',
            TaskEvent::UPDATE => 'onTaskUpdate',
            TaskEvent::DELETE => 'onTaskDelete',
            TaskEvent::PROGRESS => 'onTaskStatusProgress',
            TaskEvent::PLANNED => 'onTaskStatusPlanned',
            TaskEvent::COMPLETED => 'onTaskStatusCompleted',
            TaskEvent::REJECTED => 'onTaskStatusRejected',
            TaskEvent::APPROVED => 'onTaskStatusApproved',
        ];
    }

    public function onTaskCreate(TaskEvent $event): void
    {
        $task = $event->task;
        $task->addLog(TaskLog::logCreate($task, $event->user));
        $this->taskRepository->save($task);
    }

    public function onTaskUpdate(TaskEvent $event): void
    {
        if (0 === \count($event->changeSet)) {
            return;
        }

        $task = $event->task;
        $task->addLog(TaskLog::logUpdate($task, $event->user, $event->changeSet));
        $this->taskRepository->save($task);
    }

    public function onTaskDelete(TaskEvent $event): void
    {
    }

    public function onTaskStatusProgress(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_PROGRESS);
    }

    public function onTaskStatusPlanned(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_PLANNED);
    }

    public function onTaskStatusCompleted(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_COMPLETED);
    }

    public function onTaskStatusRejected(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_REJECTED);
    }

    public function onTaskStatusApproved(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_APPROVED);
    }

    private function updateStatus(TaskEvent $event, string $status): void
    {
        $task = $event->task;
        $task->setStatus($status);

        $task->addLog(TaskLog::logStatusUpdate($event->task, $event->user, $event->comment));
        $this->taskRepository->save($task);
    }
}
