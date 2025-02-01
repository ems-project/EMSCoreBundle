<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
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
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\PublishService;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PublishService $publishService,
        private readonly EnvironmentService $environmentService,
        private readonly ManagerRegistry $doctrine,
        private readonly NotificationService $notificationService,
        private readonly DashboardManager $dashboardManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly int $pagingSize,
        private readonly string $templateNamespace
    ) {
    }

    public function ajaxNotification(Request $request): Response
    {
        $em = $this->doctrine->getManager();

        $templateId = $request->request->getInt('templateId');
        $environmentName = Type::string($request->request->get('environmentName'));
        $ctId = $request->request->getInt('contentTypeId');
        $ouuid = Type::string($request->request->get('ouuid'));

        /** @var EnvironmentRepository $repositoryEnv */
        $repositoryEnv = $em->getRepository(Environment::class);
        /** @var Environment|null $env */
        $env = $repositoryEnv->findOneByName($environmentName);

        if (null === $env) {
            throw new NotFoundHttpException('Unknown environment');
        }

        /** @var ContentTypeRepository $repositoryCt */
        $repositoryCt = $em->getRepository(ContentType::class);
        /** @var ContentType|null $ct */
        $ct = $repositoryCt->findById($ctId);

        if (null === $ct) {
            throw new NotFoundHttpException('Content type not found');
        }

        /** @var RevisionRepository $repositoryRev */
        $repositoryRev = $em->getRepository(Revision::class);
        /** @var Revision|null $revision */
        $revision = $repositoryRev->findByOuuidAndContentTypeAndEnvironment($ct, $ouuid, $env);
        if (null === $revision) {
            throw new NotFoundHttpException('Unknown revision');
        }

        $success = $this->notificationService->addNotification($ct->getActionById((int) $templateId), $revision, $env);

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => $success,
        ]);
    }

    public function cancelNotifications(Notification $notification): Response
    {
        $this->notificationService->setStatus($notification, 'cancelled');

        return $this->redirectToRoute('notifications.sent');
    }

    public function acknowledgeNotifications(Notification $notification): Response
    {
        $this->notificationService->setStatus($notification, 'acknowledged');

        return $this->redirectToRoute('notifications.inbox');
    }

    public function treatNotifications(Request $request): Response
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
        $publishIn = null;
        if (null !== $publishTo = $treatNotification->getPublishTo()) {
            $publishIn = $this->environmentService->getAliasByName($publishTo);
        }

        foreach ($treatNotification->getNotifications() as $notificationId => $true) {
            if (null === $notification = $this->notificationRepository->find($notificationId)) {
                $this->logger->error('log.notification.notification_not_found', [
                    'notification_id' => $notificationId,
                ]);
                continue;
            }

            if ($publishIn) {
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

    public function menuNotification(): Response
    {
        return $this->render("@$this->templateNamespace/notification/menu.html.twig", [
            'counter' => $this->notificationService->menuNotification(),
            'dashboardMenu' => $this->dashboardManager->getNotificationMenu(),
        ]);
    }

    public function listNotifications(string $folder, Request $request): Response
    {
        $filters = $request->query->all('notification_form');

        $notificationFilter = new NotificationFilter();

        $form = $this->createForm(NotificationFormType::class, $notificationFilter, [
            'method' => 'GET',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // TODO: what for?
            $form->getData();
        }

        $countRejected = $this->notificationService->countRejected();
        $countPending = $this->notificationService->countPending();
        $countSent = $this->notificationService->countSent();
        $count = $countRejected + $countPending;

        // for pagination
        $paging_size = Type::integer($this->pagingSize);
        $page = $request->query->getInt('page', 1);

        $rejectedNotifications = [];
        if ('sent' == $folder) {
            $notifications = $this->notificationService->listSentNotifications(($page - 1) * $paging_size, $paging_size, $notificationFilter);
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

        return $this->render("@$this->templateNamespace/notification/list.html.twig", [
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
