<?php

namespace EMS\CoreBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use EMS\CommonBundle\Common\Standard\Type;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
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
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\PublishService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationController extends AbstractController
{
    private PublishService $publishService;
    private EnvironmentService $environmentService;
    private NotificationService $notificationService;
    private ManagerRegistry $doctrine;
    private LoggerInterface $logger;
    private DashboardManager $dashboardManager;

    public function __construct(
        LoggerInterface $logger,
        PublishService $publishService,
        EnvironmentService $environmentService,
        ManagerRegistry $doctrine,
        NotificationService $notificationService,
        DashboardManager $dashboardManager)
    {
        $this->logger = $logger;
        $this->environmentService = $environmentService;
        $this->notificationService = $notificationService;
        $this->publishService = $publishService;
        $this->doctrine = $doctrine;
        $this->dashboardManager = $dashboardManager;
    }

    public function ajaxNotificationAction(Request $request): Response
    {
        $em = $this->doctrine->getManager();

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

        $success = $this->notificationService->addNotification(\intval($templateId), $revision, $env);

        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => $success,
        ]);
    }

    public function cancelNotificationsAction(Notification $notification): Response
    {
        $this->notificationService->setStatus($notification, 'cancelled');

        return $this->redirectToRoute('notifications.sent');
    }

    public function acknowledgeNotificationsAction(Notification $notification): Response
    {
        $this->notificationService->setStatus($notification, 'acknowledged');

        return $this->redirectToRoute('notifications.inbox');
    }

    public function treatNotificationsAction(Request $request): Response
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

        $publishIn = $this->environmentService->getAliasByName($treatNotification->getPublishTo());

        foreach ($treatNotification->getNotifications() as $notificationId => $true) {
            /** @var Notification $notification */
            $notification = $repositoryNotification->find($notificationId);
            if (empty($notification)) {
                $this->logger->error('log.notification.notification_not_found', [
                    'notification_id' => $notificationId,
                ]);
                continue;
            }

            if (!empty($publishIn)) {
                $this->publishService->publish($notification->getRevision(), $publishIn);
            }

            if ($treatNotification->getAccept()) {
                $this->notificationService->accept($notification, $treatNotification);
            }

            if ($treatNotification->getReject()) {
                $this->notificationService->reject($notification, $treatNotification);
            }
        }

        return $this->redirectToRoute('notifications.inbox');
    }

    public function menuNotificationAction(): Response
    {
        return $this->render('@EMSCore/notification/menu.html.twig', [
            'counter' => $this->notificationService->menuNotification(),
            'dashboardMenu' => $this->dashboardManager->getNotificationMenu(),
        ]);
    }

    public function listNotificationsAction(string $folder, Request $request): Response
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

        $countRejected = $this->notificationService->countRejected();
        $countPending = $this->notificationService->countPending();
        $countSent = $this->notificationService->countSent();
        $count = $countRejected + $countPending;

        // for pagination
        $paging_size = Type::integer($this->getParameter('ems_core.paging_size'));
        if (null != $request->query->get('page')) {
            $page = $request->query->get('page');
        } else {
            $page = 1;
        }

        $rejectedNotifications = [];
        if ('sent' == $folder) {
            $notifications = $this->notificationService->listSentNotifications(($page - 1) * $paging_size, $paging_size, $filters);
            $lastPage = \ceil($countSent / $paging_size);
        } else {
            $notifications = $this->notificationService->listInboxNotifications(($page - 1) * $paging_size, $paging_size, $filters);
            $rejectedNotifications = $this->notificationService->listRejectedNotifications(($page - 1) * $paging_size, $paging_size, $filters);
            $lastPage = \ceil(($countRejected > $countPending ? $countRejected : $countPending) / $paging_size);
        }

        $treatNotification = new TreatNotifications();

        $treatForm = $this->createForm(TreatNotificationsType::class, $treatNotification, [
                 'action' => $this->generateUrl('notification.treat', [], UrlGeneratorInterface::RELATIVE_PATH),
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
