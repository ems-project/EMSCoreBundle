<?php

namespace EMS\CoreBundle\Service;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\TreatNotifications;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionNewDraftEvent;
use EMS\CoreBundle\Event\RevisionPublishEvent;
use EMS\CoreBundle\Event\RevisionUnpublishEvent;
use EMS\CoreBundle\Repository\NotificationRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use Exception;
use Monolog\Logger;
use Swift_Message;
use Swift_TransportException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Twig\Environment as TwigEnvironment;

class NotificationService
{
    /** @var Registry */
    private $doctrine;
    /** @var UserService */
    private $userService;
    /** @var Logger */
    private $logger;
    /** @var Session */
    private $session;
    /** @var Container */
    private $container;
    /** @var DataService */
    private $dataService;
    private $sender;
    /** @var TwigEnvironment */
    private $twig;

    //** non-service members **
    /** @var OutputInterface */
    private $output;
    private $dryRun;

    private TemplateRepository $actionRepository;

    public function __construct(
        Registry $doctrine,
        TemplateRepository $actionRepository,
        UserService $userService,
        Logger $logger,
        Session $session,
        Container $container,
        DataService $dataService,
        $sender,
        TwigEnvironment $twig)
    {
        $this->doctrine = $doctrine;
        $this->actionRepository = $actionRepository;
        $this->userService = $userService;
        $this->dataService = $dataService;
        $this->logger = $logger;
        $this->session = $session;
        $this->container = $container;
        $this->twig = $twig;
        $this->output = null;
        $this->dryRun = false;
        $this->sender = $sender;
    }

    public function getAction(int $actionId): ?Template
    {
        $action = $this->actionRepository->findOneBy([
            'id' => $actionId,
            'renderOption' => 'notification',
        ]);

        return $action instanceof Template ? $action : null;
    }

    public function publishEvent(RevisionPublishEvent $event)
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Notification');
        $notifications = $repository->findByRevisionOuuidAndEnvironment($event->getRevision(), $event->getEnvironment());

        /** @var Notification $notification */
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
        $repository = $em->getRepository('EMSCoreBundle:Notification');
        $notifications = $repository->findByRevisionOuuidAndEnvironment($event->getRevision(), $event->getEnvironment());

        foreach ($notifications as $notification) {
            $this->setStatus($notification, 'aborted', 'warning');
        }
    }

    public function finalizeDraftEvent(RevisionFinalizeDraftEvent $event): void
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Notification');
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
        $repository = $em->getRepository('EMSCoreBundle:Notification');
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

    public function setOutput($output)
    {
        $this->output = $output;
    }

    public function setDryRun($dryRun)
    {
        $this->dryRun = $dryRun;
    }

    public function setStatus(Notification $notification, $status, $level = 'notice')
    {
        //TODO: tests rights to do it
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
    public function addNotification(int $templateId, Revision $revision, Environment $environment, ?string $username = null): ?bool
    {
        $out = false;
        try {
            $em = $this->doctrine->getManager();

            /** @var TemplateRepository $repository */
            $repository = $em->getRepository('EMSCoreBundle:Template');
            /** @var Template|null $template */
            $template = $repository->findOneById($templateId);

            if (null === $template) {
                throw new NotFoundHttpException('Unknown template');
            }

            $notification = new Notification();
            $notification->setStatus('pending');

            $em = $this->doctrine->getManager();
            /** @var NotificationRepository $repository */
            $repository = $em->getRepository('EMSCoreBundle:Notification');

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
            $sentTimestamp = new DateTime();
            $notification->setSentTimestamp($sentTimestamp);

            $notification->setEnvironment($environment);

            $notification->setRevision($revision);

            if ($username) {
                $notification->setUsername($username);
            } else {
                $notification->setUsername($this->userService->getCurrentUser()->getUsername());
            }

            $em->persist($notification);
            $em->flush();

            try {
                $this->sendEmail($notification);
            } catch (Throwable $e) {
            }

            $this->logger->notice('service.notification.send', [
                'notification_name' => $notification->getTemplate()->getName(),
                EmsFields::LOG_CONTENTTYPE_FIELD => $notification->getRevision()->getContentType(),
                EmsFields::LOG_OUUID_FIELD => $notification->getRevision()->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $notification->getRevision()->getId(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
            ]);
            $out = true;
        } catch (Exception $e) {
            $this->logger->error('service.notification.send_error', [
                'notification_id' => $templateId,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
        }

        return $out;
    }

    /**
     * Call to display notifications in header menu.
     *
     * @param ?array $filters
     *
     * @return int
     */
    public function menuNotification($filters = null)
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
        $repository = $em->getRepository('EMSCoreBundle:Notification');

        $count = $repository->countPendingByUserRoleAndCircle($this->userService->getCurrentUser(), $contentTypes, $environments, $templates);
        $count += $repository->countRejectedForUser($this->userService->getCurrentUser());

        return $count;
    }

    public function countPending()
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Notification');

        return $repository->countPendingByUserRoleAndCircle($this->userService->getCurrentUser());
    }

    public function countSent()
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Notification');

        return $repository->countForSent($this->userService->getCurrentUser());
    }

    public function countRejected()
    {
        $em = $this->doctrine->getManager();
        /** @var NotificationRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Notification');

        return $repository->countRejectedForUser($this->userService->getCurrentUser());
    }

    public function listRejectedNotifications($from, $limit, $filters = null)
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
        $repository = $em->getRepository('EMSCoreBundle:Notification');
        $notifications = $repository->findRejectedForUser($this->userService->getCurrentUser(), $from, $limit, $contentTypes, $environments, $templates);

        return $notifications;
    }

    /**
     * Call to generate list of notifications.
     *
     * @param int    $from
     * @param int    $limit
     * @param ?array $filters
     *
     * @return array
     * @return Notification[]
     */
    public function listInboxNotifications($from, $limit, $filters = null)
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
        $repository = $em->getRepository('EMSCoreBundle:Notification');
        $notifications = $repository->findByPendingAndUserRoleAndCircle($this->userService->getCurrentUser(), $from, $limit, $contentTypes, $environments, $templates);

        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            $result = $repository->countNotificationByUuidAndContentType($notification->getRevision()->giveOuuid(), $notification->getRevision()->giveContentType());

            $notification->setCounter($result);
        }

        return $notifications;
    }

    /**
     * Call to generate list of notifications.
     *
     * @param int    $from
     * @param int    $limit
     * @param ?array $filters
     *
     * @return Notification[]
     */
    public function listArchivesNotifications($from, $limit, $filters = null)
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
        $repository = $em->getRepository('EMSCoreBundle:Notification');
        $notifications = $repository->findByPendingAndUserRoleAndCircle($this->userService->getCurrentUser(), $from, $limit, $contentTypes, $environments, $templates);

        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            $result = $repository->countNotificationByUuidAndContentType($notification->getRevision()->giveOuuid(), $notification->getRevision()->giveContentType());

            $notification->setCounter($result);
        }

        return $notifications;
    }

    /**
     * @param ?array<mixed> $filters
     *
     * @return Notification[]
     */
    public function listSentNotifications(int $from, int $limit, array $filters = null): array
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
        $repository = $em->getRepository('EMSCoreBundle:Notification');
        $notifications = $repository->findByPendingAndRoleAndCircleForUserSent($this->userService->getCurrentUser(), $from, $limit, $contentTypes, $environments, $templates);

//         /**@var Notification $notification*/
//         foreach ($notifications as $notification) {
//             $result = $repository->countNotificationByUuidAndContentType($notification->getRevision()->getOuuid(), $notification->getRevision()->getContentType());

//             $notification->setCounter($result);
//         }

        return $notifications;
    }

    private function response(Notification $notification, TreatNotifications $treatNotifications, $status)
    {
        $notification->setResponseText($treatNotifications->getResponse());
        $notification->setResponseTimestamp(new DateTime());
        $notification->setResponseBy($this->userService->getCurrentUser()->getUsername());
        $notification->setStatus($status);
        $em = $this->doctrine->getManager();
        $em->persist($notification);
        $em->flush();

        try {
            $this->sendEmail($notification);
        } catch (Throwable $e) {
        }

        $this->logger->notice('service.notification.treated', [
            'notification_name' => $notification->getTemplate()->getName(),
            EmsFields::LOG_CONTENTTYPE_FIELD => $notification->getRevision()->getContentType(),
            EmsFields::LOG_OUUID_FIELD => $notification->getRevision()->getOuuid(),
            EmsFields::LOG_REVISION_ID_FIELD => $notification->getRevision()->getId(),
            EmsFields::LOG_ENVIRONMENT_FIELD => $notification->getEnvironment()->getName(),
            'status' => $notification->getStatus(),
        ]);

        $em->clear(); //bulk treat issue
    }

    public function accept(Notification $notification, TreatNotifications $treatNotifications)
    {
        $this->response($notification, $treatNotifications, 'accepted');
    }

    public function reject(Notification $notification, TreatNotifications $treatNotifications)
    {
        $this->response($notification, $treatNotifications, 'rejected');
    }

    public static function usersToEmailAddresses($users)
    {
        $out = [];
        /** @var UserInterface $user */
        foreach ($users as $user) {
            if ($user->getEmailNotification() && $user->isEnabled()) {
                $out[$user->getEmail()] = $user->getDisplayName();
            }
        }

        return $out;
    }

    /**
     * @throws Throwable
     */
    public function sendEmail(Notification $notification)
    {
        $fromCircles = $this->dataService->getDataCircles($notification->getRevision());

        $toCircles = \array_unique(\array_merge($fromCircles, $notification->getTemplate()->getCirclesTo()));

        $fromUser = $this->usersToEmailAddresses([$this->userService->getUser($notification->getUsername())]);
        $toUsers = $this->usersToEmailAddresses($this->userService->getUsersForRoleAndCircles($notification->getTemplate()->getRoleTo(), $toCircles));
        $ccUsers = $this->usersToEmailAddresses($this->userService->getUsersForRoleAndCircles($notification->getTemplate()->getRoleCc(), $toCircles));

        $message = (new Swift_Message());

        $params = [
                'notification' => $notification,
                'source' => $notification->getRevision()->getRawData(),
                'object' => $notification->getRevision()->buildObject(),
                'status' => $notification->getStatus(),
                'environment' => $notification->getEnvironment(),
        ];

        if ('pending' == $notification->getStatus()) {
            //it's a notification
            try {
                $body = $this->twig->createTemplate($notification->getTemplate()->getBody())->render($params);
            } catch (Exception $e) {
                $body = 'Error in body template: '.$e->getMessage();
            }

            $message->setSubject($notification->getTemplate().' for '.$notification->getRevision())
                ->setFrom($this->sender['address'], $this->sender['sender_name'])
                ->setTo($toUsers)
                ->setCc(\array_unique(\array_merge($ccUsers, $fromUser)))
                ->setBody($body, empty($notification->getTemplate()->getEmailContentType()) ? 'text/html' : $notification->getTemplate()->getEmailContentType());
            $notification->setEmailed(new DateTime());
        } else {
            //it's a notification
            try {
                $body = $this->twig->createTemplate($notification->getTemplate()->getResponseTemplate())->render($params);
            } catch (Exception $e) {
                $body = 'Error in response template: '.$e->getMessage();
            }

            //it's a reminder
            $message->setSubject($notification->getTemplate().' for '.$notification->getRevision().' has been '.$notification->getStatus())
                ->setFrom($this->sender['address'], $this->sender['sender_name'])
                ->setTo($fromUser)
                ->setCc(\array_unique(\array_merge($ccUsers, $toUsers)))
                ->setBody($body, 'text/html');
            $notification->setResponseEmailed(new DateTime());
        }

        if (!$this->dryRun) {
            $em = $this->doctrine->getManager();
            try {
                /**@Swift_Mailer $mailer*/
                $mailer = $this->container->get('mailer');
                $mailer->send($message);
                $em->persist($notification);
                $em->flush();
            } catch (Swift_TransportException $e) {
            }
        }
    }
}
