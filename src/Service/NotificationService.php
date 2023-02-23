<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\NotificationFilter;
use EMS\CoreBundle\Entity\Form\TreatNotifications;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionNewDraftEvent;
use EMS\CoreBundle\Event\RevisionPublishEvent;
use EMS\CoreBundle\Event\RevisionUnpublishEvent;
use EMS\CoreBundle\Exception\SkipNotificationException;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Repository\NotificationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;

class NotificationService
{
    private bool $dryRun = false;

    public function __construct(private readonly Registry $doctrine, private readonly UserService $userService, private readonly LoggerInterface $logger, private readonly DataService $dataService, private readonly MailerService $mailerService, private readonly TwigEnvironment $twig)
    {
    }

    public function publishEvent(RevisionPublishEvent $event): void
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);
        $notifications = $repository->findByRevisionOuuidAndEnvironment($event->getRevision(), $event->getEnvironment());

        foreach ($notifications as $notification) {
            if ($notification->getRevision() !== $event->getRevision()) {
                $this->setStatus($notification, 'aborted', 'warning');
            }
        }
    }

    public function unpublishEvent(RevisionUnpublishEvent $event): void
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);
        $notifications = $repository->findByRevisionOuuidAndEnvironment($event->getRevision(), $event->getEnvironment());

        foreach ($notifications as $notification) {
            $this->setStatus($notification, 'aborted', 'warning');
        }
    }

    public function finalizeDraftEvent(RevisionFinalizeDraftEvent $event): void
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);
        $notifications = $repository->findByRevisionOuuidAndEnvironment($event->getRevision(), $event->getRevision()->giveContentType()->giveEnvironment());

        foreach ($notifications as $notification) {
            if ($notification->getRevision() !== $event->getRevision()) {
                $this->setStatus($notification, 'aborted', 'warning');
            }
        }
    }

    public function newDraftEvent(RevisionNewDraftEvent $event): void
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);
        $notifications = $repository->findByRevisionOuuidAndEnvironment($event->getRevision(), $event->getRevision()->giveContentType()->giveEnvironment());

        foreach ($notifications as $notification) {
            $this->logger->warning('service.notification.notification_will_be_lost_finalize', [
                'notification_name' => $notification->getTemplate()->getName(),
                EmsFields::LOG_CONTENTTYPE_FIELD => $event->getRevision()->getContentType(),
                EmsFields::LOG_OUUID_FIELD => $event->getRevision()->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $event->getRevision()->getId(),
            ]);
        }
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function setStatus(Notification $notification, string $status, string $level = 'notice'): self
    {
        // TODO: tests rights to do it
        $userName = $this->userService->getCurrentUser()->getUserName();

        $notification->setStatus($status);
        if ('acknowledged' != $status) {
            $notification->setResponseBy($userName);
        }

        $em = $this->doctrine->getManager();
        $em->persist($notification);
        $em->flush();

        if ('error' === $level) {
            $this->logger->error('service.notification.update', [
                'notification_name' => $notification->getTemplate()->getName(),
                EmsFields::LOG_CONTENTTYPE_FIELD => $notification->getRevision()->getContentType(),
                EmsFields::LOG_OUUID_FIELD => $notification->getRevision()->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $notification->getRevision()->getId(),
                'notification_status' => $status,
            ]);
        } elseif ('warning' === $level) {
            $this->logger->warning('service.notification.update', [
                'notification_name' => $notification->getTemplate()->getName(),
                EmsFields::LOG_CONTENTTYPE_FIELD => $notification->getRevision()->getContentType(),
                EmsFields::LOG_OUUID_FIELD => $notification->getRevision()->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $notification->getRevision()->getId(),
                'notification_status' => $status,
            ]);
        } else {
            $this->logger->notice('service.notification.update', [
                'notification_name' => $notification->getTemplate()->getName(),
                EmsFields::LOG_CONTENTTYPE_FIELD => $notification->getRevision()->getContentType(),
                EmsFields::LOG_OUUID_FIELD => $notification->getRevision()->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $notification->getRevision()->getId(),
                'notification_status' => $status,
            ]);
        }

        return $this;
    }

    /**
     * Call addNotification when click on a request.
     */
    public function addNotification(Template $template, Revision $revision, Environment $environment, ?string $username = null): ?bool
    {
        $out = false;
        try {
            if (!\in_array($template->getRenderOption(), [RenderOptionType::NOTIFICATION])) {
                throw new \RuntimeException(\sprintf('Unexpected %s action', $template->getRenderOption()));
            }
            $notification = new Notification();
            $notification->setStatus('pending');

            $em = $this->doctrine->getManager();
            /** @var NotificationRepository $repository */
            $repository = $em->getRepository(Notification::class);

            $alreadyPending = $repository->findBy([
                    'template' => $template,
                    'revision' => $revision,
                    'environment' => $environment,
                    'status' => 'pending',
            ]);

            if (!empty($alreadyPending)) {
                /** @var Notification $alreadyPending */
                $alreadyPending = $alreadyPending[0];
                $this->logger->warning('service.notification.another_one_is_pending', [
                    'label' => $alreadyPending->getRevision()->getLabel(),
                    'notification_name' => $alreadyPending->getTemplate()->getName(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $alreadyPending->getRevision()->getContentType(),
                    EmsFields::LOG_OUUID_FIELD => $alreadyPending->getRevision()->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $alreadyPending->getRevision()->getId(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    'notification_username' => $alreadyPending->getUsername(),
                ]);

                return null;
            }

            $notification->setTemplate($template);
            $sentTimestamp = new \DateTime();
            $notification->setSentTimestamp($sentTimestamp);

            $notification->setEnvironment($environment);

            $notification->setRevision($revision);

            if ($username) {
                $notification->setUsername($username);
            } else {
                $notification->setUsername($this->userService->getCurrentUser()->getUsername());
            }

            $this->sendEmail($notification);

            $em->persist($notification);
            $em->flush();

            $this->logger->notice('service.notification.send', [
                'label' => $notification->getRevision()->getLabel(),
                'notification_name' => $notification->getTemplate()->getName(),
                EmsFields::LOG_CONTENTTYPE_FIELD => $notification->getRevision()->getContentType(),
                EmsFields::LOG_OUUID_FIELD => $notification->getRevision()->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $notification->getRevision()->getId(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);
            $out = true;
        } catch (SkipNotificationException $e) {
            $this->logger->warning($e->getMessage(), [
                'action_name' => $template->getName(),
                'action_label' => $template->getLabel(),
                'contenttype_name' => $revision->giveContentType(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('service.notification.send_error', [
                'action_name' => $template->getName(),
                'action_label' => $template->getLabel(),
                'contenttype_name' => $revision->giveContentType(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        }

        return $out;
    }

    /**
     * @param ?array<string, mixed> $filters
     */
    public function menuNotification(?array $filters = null): int
    {
        $contentTypes = null;
        $environments = null;
        $templates = null;

        if (null != $filters) {
            if (isset($filters['contentType'])) {
                $contentTypes = $filters['contentType'];
            } elseif (isset($filters['environment'])) {
                $environments = $filters['environment'];
            } elseif (isset($filters['template'])) {
                $templates = $filters['template'];
            }
        }

        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);

        $count = $repository->countPendingByUserRoleAndCircle($this->userService->getCurrentUser(), $contentTypes, $environments, $templates);
        $count += $repository->countRejectedForUser($this->userService->getCurrentUser());

        return $count;
    }

    public function countPending(): int
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);

        return $repository->countPendingByUserRoleAndCircle($this->userService->getCurrentUser());
    }

    public function countSent(): int
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);

        return $repository->countForSent($this->userService->getCurrentUser());
    }

    public function countRejected(): int
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);

        return $repository->countRejectedForUser($this->userService->getCurrentUser());
    }

    /**
     * @param ?array<mixed> $filters
     *
     * @return Notification[]
     */
    public function listRejectedNotifications(int $from, int $limit, array $filters = null): array
    {
        $contentTypes = null;
        $environments = null;
        $templates = null;

        if (null != $filters) {
            if (isset($filters['contentType'])) {
                $contentTypes = $filters['contentType'];
            } elseif (isset($filters['environment'])) {
                $environments = $filters['environment'];
            } elseif (isset($filters['template'])) {
                $templates = $filters['template'];
            }
        }

        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);

        return $repository->findRejectedForUser($this->userService->getCurrentUser(), $from, $limit, $contentTypes, $environments, $templates);
    }

    /**
     * Call to generate list of notifications.
     *
     * @param ?array<mixed> $filters
     *
     * @return Notification[]
     */
    public function listInboxNotifications(int $from, int $limit, array $filters = null): array
    {
        $contentTypes = null;
        $environments = null;
        $templates = null;

        if (null != $filters) {
            if (isset($filters['contentType'])) {
                $contentTypes = $filters['contentType'];
            } elseif (isset($filters['environment'])) {
                $environments = $filters['environment'];
            } elseif (isset($filters['template'])) {
                $templates = $filters['template'];
            }
        }

        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);
        $notifications = $repository->findByPendingAndUserRoleAndCircle($this->userService->getCurrentUser(), $from, $limit, $contentTypes, $environments, $templates);

        foreach ($notifications as $notification) {
            $result = $repository->countNotificationByUuidAndContentType($notification->getRevision()->giveOuuid(), $notification->getRevision()->giveContentType());

            $notification->setCounter($result);
        }

        return $notifications;
    }

    /**
     * Call to generate list of notifications.
     *
     * @param ?array<mixed> $filters
     *
     * @return Notification[]
     */
    public function listArchivesNotifications(int $from, int $limit, array $filters = null): array
    {
        $contentTypes = null;
        $environments = null;
        $templates = null;

        if (null != $filters) {
            if (isset($filters['contentType'])) {
                $contentTypes = $filters['contentType'];
            } elseif (isset($filters['environment'])) {
                $environments = $filters['environment'];
            } elseif (isset($filters['template'])) {
                $templates = $filters['template'];
            }
        }

        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);
        $notifications = $repository->findByPendingAndUserRoleAndCircle($this->userService->getCurrentUser(), $from, $limit, $contentTypes, $environments, $templates);

        foreach ($notifications as $notification) {
            $result = $repository->countNotificationByUuidAndContentType($notification->getRevision()->giveOuuid(), $notification->getRevision()->giveContentType());

            $notification->setCounter($result);
        }

        return $notifications;
    }

    /**
     * @return Notification[]
     */
    public function listSentNotifications(int $from, int $limit, NotificationFilter $notificationFilter): array
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository(Notification::class);

        return $repository->findByPendingAndRoleAndCircleForUserSent($this->userService->getCurrentUser(), $from, $limit, $notificationFilter);
    }

    private function response(Notification $notification, TreatNotifications $treatNotifications, string $status): void
    {
        $notification->setResponseText($treatNotifications->getResponse());
        $notification->setResponseTimestamp(new \DateTime());
        $notification->setResponseBy($this->userService->getCurrentUser()->getUsername());
        $notification->setStatus($status);
        $em = $this->doctrine->getManager();
        $em->persist($notification);
        $em->flush();

        try {
            $this->sendEmail($notification);
        } catch (\Throwable) {
        }

        $this->logger->notice('service.notification.treated', [
            'notification_name' => $notification->getTemplate()->getName(),
            EmsFields::LOG_CONTENTTYPE_FIELD => $notification->getRevision()->getContentType(),
            EmsFields::LOG_OUUID_FIELD => $notification->getRevision()->getOuuid(),
            EmsFields::LOG_REVISION_ID_FIELD => $notification->getRevision()->getId(),
            EmsFields::LOG_ENVIRONMENT_FIELD => $notification->getEnvironment()->getName(),
            'status' => $notification->getStatus(),
            'label' => $notification->getRevision()->getLabel(),
        ]);
    }

    public function accept(Notification $notification, TreatNotifications $treatNotifications): void
    {
        $this->response($notification, $treatNotifications, 'accepted');
    }

    public function reject(Notification $notification, TreatNotifications $treatNotifications): void
    {
        $this->response($notification, $treatNotifications, 'rejected');
    }

    /**
     * @param UserInterface[] $users
     *
     * @return array<string, Address>
     */
    private static function usersToEmailAddresses(array $users): array
    {
        $out = [];

        foreach ($users as $user) {
            if ($user->getEmailNotification() && $user->isEnabled()) {
                $out[$user->getEmail()] = new Address($user->getEmail(), $user->getDisplayName());
            }
        }

        return $out;
    }

    /**
     * @throws \Throwable
     */
    public function sendEmail(Notification $notification): void
    {
        $fromCircles = $this->dataService->getDataCircles($notification->getRevision());

        $toCircles = \array_unique(\array_merge($fromCircles, $notification->getTemplate()->getCirclesTo()));

        $fromUser = self::usersToEmailAddresses(\array_filter([$this->userService->getUser($notification->getUsername())]));
        $toUsers = self::usersToEmailAddresses($this->userService->getUsersForRoleAndCircles($notification->getTemplate()->getRoleTo(), $toCircles));
        $ccUsers = self::usersToEmailAddresses($this->userService->getUsersForRoleAndCircles($notification->getTemplate()->getRoleCc(), $toCircles));

        $email = new Email();
        $params = [
            'notification' => $notification,
            'source' => $notification->getRevision()->getRawData(),
            'object' => $notification->getRevision()->buildObject(),
            'status' => $notification->getStatus(),
            'environment' => $notification->getEnvironment(),
        ];

        if ('pending' == $notification->getStatus()) {
            try {
                $body = $this->twig->createTemplate($notification->getTemplate()->getBody())->render($params);
            } catch (\Throwable $e) {
                $previousException = $e->getPrevious();
                if ($previousException instanceof SkipNotificationException) {
                    throw $previousException;
                }
                throw $e;
            }

            $email
                ->subject($notification->getTemplate().' for '.$notification->getRevision())
                ->to(...\array_values($toUsers));

            $cc = [...$ccUsers, ...$fromUser];
            if ($cc) {
                $email->cc(...\array_values($cc));
            }

            $notification->setEmailed(new \DateTime());
        } else {
            try {
                $body = $this->twig->createTemplate($notification->getTemplate()->getResponseTemplate())->render($params);
            } catch (\Exception $e) {
                $body = 'Error in response template: '.$e->getMessage();
            }

            // it's a reminder
            $email
                ->subject($notification->getTemplate().' for '.$notification->getRevision().' has been '.$notification->getStatus())
                ->to(...\array_values($fromUser));

            $cc = [...$ccUsers, ...$toUsers];
            if ($cc) {
                $email->cc(...\array_values($cc));
            }

            $notification->setResponseEmailed(new \DateTime());
        }

        $contentType = $notification->getTemplate()->getEmailContentType() ?? 'text/plain';
        if ('text/html' === $contentType) {
            $email->html($body);
        } else {
            $email->text($body);
        }

        if (!$this->dryRun) {
            $em = $this->doctrine->getManager();
            try {
                $this->mailerService->sendMail($email);
                $em->persist($notification);
                $em->flush();
            } catch (TransportExceptionInterface) {
            }
        }
    }
}
