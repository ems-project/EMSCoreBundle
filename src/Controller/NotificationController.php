<?php

namespace EMS\CoreBundle\Controller;

use Doctrine\ORM\NonUniqueResultException;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\NotificationFilter;
use EMS\CoreBundle\Entity\Form\TreatNotifications;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\NotificationFormType;
use EMS\CoreBundle\Form\Form\TreatNotificationsType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\NotificationRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class NotificationController extends AppController
{
    /**
     * @return Response
     *
     * @throws NonUniqueResultException
     *
     * @Route("/notification/add/{objectId}.json", name="notification.ajaxnotification", methods={"POST"})
     */
    public function ajaxNotificationAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $templateId = $request->request->get('templateId');
        $environmentName = $request->request->get('environmentName');
        $ctId = $request->request->get('contentTypeId');
        $ouuid = $request->request->get('ouuid');

        /** @var EnvironmentRepository $repositoryEnv */
        $repositoryEnv = $em->getRepository('EMSCoreBundle:Environment');
        /** @var Environment|null $env */
        $env = $repositoryEnv->findOneByName($environmentName);

        if (null === $env) {
            throw new NotFoundHttpException('Unknown environment');
        }

        /** @var ContentTypeRepository $repositoryCt */
        $repositoryCt = $em->getRepository('EMSCoreBundle:ContentType');
        /** @var ContentType|null $ct */
        $ct = $repositoryCt->findById($ctId);

        if (null === $ct) {
            throw new NotFoundHttpException('Content type not found');
        }

        /** @var RevisionRepository $repositoryRev */
        $repositoryRev = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repositoryRev->findByOuuidAndContentTypeAndEnvironment($ct, $ouuid, $env);
        if (null === $revision) {
            throw new NotFoundHttpException('Unknown revision');
        }

        $success = $this->getNotificationService()->addNotification($templateId, $revision, $env);

        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => $success,
        ]);
    }

    /**
     * @return RedirectResponse
     *
     * @Route("/notification/cancel/{notification}", name="notification.cancel", methods={"POST"})
     */
    public function cancelNotificationsAction(Notification $notification)
    {
        $this->getNotificationService()->setStatus($notification, 'cancelled');

        return $this->redirectToRoute('notifications.sent');
    }

    /**
     * @return RedirectResponse
     *
     * @Route("/notification/acknowledge/{notification}", name="notification.acknowledge", methods={"POST"})
     */
    public function acknowledgeNotificationsAction(Notification $notification)
    {
        $this->getNotificationService()->setStatus($notification, 'acknowledged');

        return $this->redirectToRoute('notifications.inbox');
    }

    /**
     * @return RedirectResponse
     *
     * @Route("/notification/treat", name="notification.treat", methods={"POST"})
     */
    public function treatNotificationsAction(Request $request)
    {
        $treatNotification = new TreatNotifications();
        $form = $this->createForm(TreatNotificationsType::class, $treatNotification, [
        ]);
        $form->handleRequest($request);
        /** @var TreatNotifications $treatNotification */
        $treatNotification = $form->getNormData();
        $accept = $form->get('accept');
        if ($accept instanceof ClickableInterface) {
            $treatNotification->setAccept($accept->isClicked());
        }
        $reject = $form->get('reject');
        if ($reject instanceof ClickableInterface) {
            $treatNotification->setReject($reject->isClicked());
        }

        $em = $this->getDoctrine()->getManager();
        $repositoryNotification = $em->getRepository('EMSCoreBundle:Notification');

        $publishIn = $this->getEnvironmentService()->getAliasByName($treatNotification->getPublishTo());

        foreach ($treatNotification->getNotifications() as $notificationId => $true) {
            /** @var Notification $notification */
            $notification = $repositoryNotification->find($notificationId);
            if (empty($notification)) {
                $this->getLogger()->error('log.notification.notification_not_found', [
                    'notification_id' => $notificationId,
                ]);
                continue;
            }

            if (!empty($publishIn)) {
                $this->getPublishService()->publish($notification->getRevision(), $publishIn);
            }

            if ($treatNotification->getAccept()) {
                $this->get('ems.service.notification')->accept($notification, $treatNotification);
            }

            if ($treatNotification->getReject()) {
                $this->get('ems.service.notification')->reject($notification, $treatNotification);
            }
        }

        return $this->redirectToRoute('notifications.inbox');
    }

    /**
     * @return Response
     *
     * @Route("/notification/menu", name="notification.menu")
     */
    public function menuNotificationAction()
    {
        // TODO use a service to pass authorization_checker to repositoryNotification.
        $em = $this->getDoctrine()->getManager();
        /** @var NotificationRepository $repositoryNotification */
        $repositoryNotification = $em->getRepository('EMSCoreBundle:Notification');
        $repositoryNotification->setAuthorizationChecker($this->get('security.authorization_checker'));

        $vars['counter'] = $this->get('ems.service.notification')->menuNotification();

        return $this->render('@EMSCore/notification/menu.html.twig', $vars);
    }

    /**
     * @param string $folder
     *
     * @return Response
     *
     * @Route("/notifications/list", name="notifications.list", defaults={"folder"="inbox"})
     * @Route("/notifications/inbox", name="notifications.inbox", defaults={"folder"="inbox"})
     * @Route("/notifications/sent", name="notifications.sent", defaults={"folder"="sent"})
     */
    public function listNotificationsAction($folder, Request $request)
    {
        $filters = $request->query->get('notification_form');

        $notificationFilter = new NotificationFilter();

        $form = $this->createForm(NotificationFormType::class, $notificationFilter, [
                 'method' => 'GET',
         ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            //TODO: what for?
            $form->getData();
        }

        //TODO: use a service to pass authorization_checker to repositoryNotification.
        $em = $this->getDoctrine()->getManager();
        /** @var NotificationRepository $repositoryNotification */
        $repositoryNotification = $em->getRepository('EMSCoreBundle:Notification');
        $repositoryNotification->setAuthorizationChecker($this->get('security.authorization_checker'));

        $countRejected = $this->getNotificationService()->countRejected();
        $countPending = $this->getNotificationService()->countPending();
        $countSent = $this->getNotificationService()->countSent();
        $count = $countRejected + $countPending;

        // for pagination
        $paging_size = $this->getParameter('ems_core.paging_size');
        if (null != $request->query->get('page')) {
            $page = $request->query->get('page');
        } else {
            $page = 1;
        }

        $rejectedNotifications = [];
        if ('sent' == $folder) {
            $notifications = $this->getNotificationService()->listSentNotifications(($page - 1) * $paging_size, $paging_size, $filters);
            $lastPage = \ceil($countSent / $paging_size);
        } else {
            $notifications = $this->getNotificationService()->listInboxNotifications(($page - 1) * $paging_size, $paging_size, $filters);
            $rejectedNotifications = $this->getNotificationService()->listRejectedNotifications(($page - 1) * $paging_size, $paging_size, $filters);
            $lastPage = \ceil(($countRejected > $countPending ? $countRejected : $countPending) / $paging_size);
        }

        $treatNotification = new TreatNotifications();

        /** @var RouterInterface $router */
        $router = $this->get('router');
        $treatForm = $this->createForm(TreatNotificationsType::class, $treatNotification, [
                 'action' => $router->generate('notification.treat', [], UrlGeneratorInterface::RELATIVE_PATH),
                 'notifications' => $notifications,
         ]);

        return $this->render('@EMSCore/notification/list.html.twig', [
                'counter' => $count,
                'notifications' => $notifications,
                'lastPage' => $lastPage,
                'paginationPath' => 'notifications.'.$folder,
                'page' => $page,
                'form' => $form->createView(),
                'treatform' => $treatForm->createView(),
                'currentFilters' => $request->query,
                'folder' => $folder,
                'countPending' => $countPending,
                'countRejected' => $countRejected,
                'rejectedNotifications' => $rejectedNotifications,
                'countSent' => $countSent,
        ]);
    }
}
