<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TaskEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TaskManager $taskManager,
        private readonly TaskMailer $taskMailer,
    ) {
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
        $task->addLog(TaskLog::logCreate($task, $event->username));
        $this->taskManager->taskSave($task);
    }

    public function onTaskUpdate(TaskEvent $event): void
    {
        if (0 === \count($event->changeSet)) {
            return;
        }

        $changeSet = $event->changeSet;
        $task = $event->task;
        $task->addLog(TaskLog::logUpdate($task, $event->username, $changeSet));
        $this->taskManager->taskSave($task);

        if ($event->isTaskCurrent()) {
            if (isset($changeSet['assignee'])) {
                $this->taskMailer->sendForEvent($event, 'assignee_changed', $changeSet['assignee'][0]);
                $this->taskMailer->sendForEvent($event, 'created', $changeSet['assignee'][1]);

                $task->addLog(TaskLog::logNewAssignee($task, $event->username));
                $this->taskManager->taskSave($task);
            } else {
                $this->taskMailer->sendForEvent($event, 'updated', $task->getAssignee());
            }
        }
    }

    public function onTaskDelete(TaskEvent $event): void
    {
        $this->taskMailer->sendForEvent($event, 'deleted', $event->task->getCreatedBy());

        if ($event->task->isStatus(TaskStatus::PROGRESS)) {
            $this->taskMailer->sendForEvent($event, 'deleted', $event->task->getAssignee());
        }
    }

    public function onTaskStatusProgress(TaskEvent $event): void
    {
        $this->updateStatus($event, TaskStatus::PROGRESS);

        if ($event->isTaskCurrent()) {
            $this->taskMailer->sendForEvent($event, 'created', $event->task->getAssignee());
        }
    }

    public function onTaskStatusPlanned(TaskEvent $event): void
    {
        $this->updateStatus($event, TaskStatus::PLANNED);
    }

    public function onTaskStatusCompleted(TaskEvent $event): void
    {
        $this->updateStatus($event, TaskStatus::COMPLETED);

        if ($event->isTaskCurrent()) {
            $this->taskMailer->sendForEvent($event, 'completed', $event->task->getCreatedBy());
        }
    }

    public function onTaskStatusRejected(TaskEvent $event): void
    {
        $this->updateStatus($event, TaskStatus::REJECTED);

        if ($event->isTaskCurrent()) {
            $this->taskMailer->sendForEvent($event, 'rejected', $event->task->getAssignee());
        }
    }

    public function onTaskStatusApproved(TaskEvent $event): void
    {
        $this->updateStatus($event, TaskStatus::APPROVED);

        if ($event->isTaskCurrent()) {
            $this->taskMailer->sendForEvent($event, 'approved', $event->task->getAssignee());
        }
    }

    private function updateStatus(TaskEvent $event, TaskStatus $status): void
    {
        $task = $event->task;
        $task->setStatus($status->value);

        $task->addLog(TaskLog::logStatusUpdate($event->task, $event->username, $event->comment));
        $this->taskManager->taskSave($task);
    }
}
