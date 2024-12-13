<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Core\Mail\MailTemplate;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskMailer
{
    private const MAIL_TEMPLATE = '/revision/task/mail.twig';

    public function __construct(
        private readonly MailerService $mailerService,
        private readonly TaskManager $taskManager,
        private readonly UserService $userService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly ?string $urlUser,
        private readonly string $templateNamespace
    ) {
    }

    public function sendForEvent(TaskEvent $event, string $type, string $receiverUsername): void
    {
        $task = $event->task;
        $revision = $event->revision;
        $senderUsername = $event->username;

        $sender = $this->userService->getUser($senderUsername);
        $receiver = $this->userService->getUser($receiverUsername);

        if (null === $receiver
            || !$receiver->getEmailNotification()
            || $receiver->getUsername() === $senderUsername) {
            return;
        }

        $context = [
            'receiver' => $receiver,
            'senderUsername' => $senderUsername,
            'senderRole' => $sender ? $this->getSenderRole($task, $sender) : null,
            'type' => $type,
            'action' => $this->translator->trans(\sprintf('task.mail.%s.action', $type), [], EMSCoreBundle::TRANS_DOMAIN),
            'task' => $task,
            'revision' => $revision,
            'comment' => $event->comment,
            'changeSet' => $event->changeSet,
            'backendUrl' => $this->urlUser,
            'documentUrl' => $this->getDocumentUrl($revision),
        ];

        $mailTemplate = $this->getMailTemplate($receiver)
            ->setSubjectBlock('task_event_mail_subject', $context)
            ->setBodyBlock('task_event_mail_body', $context);

        $this->mailerService->sendMailTemplate($mailTemplate);
    }

    /**
     * @param Revision[] $revisions
     */
    public function sendNotificationMail(string $receiverUsername, string $subject, array $revisions, int $limit): void
    {
        $receiver = $this->userService->getUser($receiverUsername);

        if (null === $receiver || !$receiver->getEmailNotification()) {
            return;
        }

        $documentUrls = \array_reduce($revisions, function ($carry, Revision $r) {
            $carry[$r->getOuuid()] = $this->getDocumentUrl($r);

            return $carry;
        }, []);

        $context = [
            'subject' => $subject,
            'receiver' => $receiver,
            'revisions' => $revisions,
            'documentUrls' => $documentUrls,
            'limit' => $limit,
        ];

        $mailTemplate = $this->getMailTemplate($receiver)
            ->setSubjectBlock('task_notification_mail_subject', $context)
            ->setBodyBlock('task_notification_mail_body', $context);

        $this->mailerService->sendMailTemplate($mailTemplate);
    }

    private function getMailTemplate(UserInterface $receiver): MailTemplate
    {
        return $this->mailerService
            ->makeMailTemplate("@$this->templateNamespace".self::MAIL_TEMPLATE)
            ->addTo($receiver->getEmail());
    }

    private function getDocumentUrl(Revision $revision): string
    {
        return $this->urlUser.$this->urlGenerator->generate(Routes::VIEW_REVISIONS, [
            'type' => $revision->giveContentType()->getName(),
            'ouuid' => $revision->getOuuid(),
        ]);
    }

    private function getSenderRole(Task $task, UserInterface $sender): ?string
    {
        return match (true) {
            ($sender->getUsername() === $task->getAssignee()) => 'assignee',
            ($sender->getUsername() === $task->getCreatedBy()) => 'creator',
            $this->taskManager->isTaskManager($sender) => 'task admin',
            default => null,
        };
    }
}
