<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TaskEventSubscriber implements EventSubscriberInterface
{
    private const MAIL_TEMPLATE = '@EMSCore/revision/task/mail.twig';

    public function __construct(private readonly TaskRepository $taskRepository, private readonly MailerService $mailerService, private readonly UserService $userService, private readonly ?string $urlUser)
    {
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
        $this->taskRepository->save($task);
    }

    public function onTaskUpdate(TaskEvent $event): void
    {
        if (0 === \count($event->changeSet)) {
            return;
        }

        $changeSet = $event->changeSet;
        $task = $event->task;
        $task->addLog(TaskLog::logUpdate($task, $event->username, $changeSet));
        $this->taskRepository->save($task);

        if ($event->isTaskCurrent()) {
            if (isset($changeSet['assignee'])) {
                $this->sendMail($event, 'assignee_changed', $changeSet['assignee'][0]);
                $this->sendMail($event, 'created', $changeSet['assignee'][1]);

                $task->addLog(TaskLog::logNewAssignee($task, $event->username));
                $this->taskRepository->save($task);
            } else {
                $this->sendMail($event, 'updated');
            }
        }
    }

    public function onTaskDelete(TaskEvent $event): void
    {
        if ($event->username !== $event->task->getCreatedBy()) {
            $this->sendMail($event, 'deleted', $event->task->getCreatedBy());
        }

        $this->sendMail($event, 'deleted');
    }

    public function onTaskStatusProgress(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_PROGRESS);

        if ($event->isTaskCurrent()) {
            $this->sendMail($event, 'created');
        }
    }

    public function onTaskStatusPlanned(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_PLANNED);
    }

    public function onTaskStatusCompleted(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_COMPLETED);

        if ($event->isTaskCurrent()) {
            $this->sendMail($event, 'completed', $event->task->getCreatedBy());
        }
    }

    public function onTaskStatusRejected(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_REJECTED);

        if ($event->isTaskCurrent()) {
            $this->sendMail($event, 'rejected');
        }
    }

    public function onTaskStatusApproved(TaskEvent $event): void
    {
        $this->updateStatus($event, Task::STATUS_APPROVED);

        if ($event->isTaskCurrent()) {
            $this->sendMail($event, 'approved');
        }
    }

    private function updateStatus(TaskEvent $event, string $status): void
    {
        $task = $event->task;
        $task->setStatus($status);

        $task->addLog(TaskLog::logStatusUpdate($event->task, $event->username, $event->comment));
        $this->taskRepository->save($task);
    }

    private function sendMail(TaskEvent $event, string $type, string $receiverUsername = null): void
    {
        $task = $event->task;
        $revision = $event->revision;
        $receiverUsername = $receiverUsername ?: $task->getAssignee();

        $receiver = $this->userService->getUser($receiverUsername);

        if (null === $receiver
            || !$receiver->getEmailNotification()
            || (Task::STATUS_COMPLETED !== $type && $event->isAssigneeIsRequester())) {
            return;
        }

        $mailTemplate = $this->mailerService->makeMailTemplate(self::MAIL_TEMPLATE);
        $mailTemplate
            ->addTo($receiver->getEmail())
            ->setSubject(\sprintf('task.mail.%s', $type), [
                '%title%' => $task->getTitle(),
                '%document%' => $event->revision->getLabel(),
            ])
            ->setBodyBlock(\sprintf('mail_%s', $type), [
                'receiver' => $receiver,
                'task' => $task,
                'revision' => $revision,
                'changeSet' => $event->changeSet,
                'backendUrl' => $this->urlUser,
            ])
        ;

        $this->mailerService->sendMailTemplate($mailTemplate);
    }
}
