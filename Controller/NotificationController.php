<?php

namespace Ems\CoreBundle\Controller;

use Ems\CoreBundle\Controller\AppController;
use Ems\CoreBundle;
use Ems\CoreBundle\Entity\ContentType;
use Ems\CoreBundle\Entity\Environment;
use Ems\CoreBundle\Entity\Form\NotificationFilter;
use Ems\CoreBundle\Entity\Form\TreatNotifications;
use Ems\CoreBundle\Entity\Notification;
use Ems\CoreBundle\Form\Form\NotificationFormType;
use Ems\CoreBundle\Form\Form\TreatNotificationsType;
use Ems\CoreBundle\Repository\ContentTypeRepository;
use Ems\CoreBundle\Repository\EnvironmentRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationController extends AppController
{
	
	/**
	 * @Route("/notification/add/{objectId}.json", name="notification.ajaxnotification"))
	 * @Method({"POST"})
	 */
	public function ajaxNotificationAction(Request $request)
	{
		$em = $this->getDoctrine()->getManager();
	
		$templateId = $request->request->get('templateId');
		$environmentName = $request->request->get('environmentName');
		$ctId = $request->request->get('contentTypeId');
		$ouuid = $request->request->get('ouuid');
		
		/** @var EnvironmentRepository $repositoryEnv */
		$repositoryEnv = $em->getRepository('Ems/CoreBundle:Environment');
		/** @var Environment $env */
		$env = $repositoryEnv->findOneByName($environmentName);
		
		if(!$env) {
			throw new NotFoundHttpException('Unknown environment');
		}
			
		/** @var ContentTypeRepository $repositoryCt */
		$repositoryCt = $em->getRepository('Ems/CoreBundle:ContentType');
		/** @var ContentType $ct */
		$ct = $repositoryCt->findOneById($ctId);
		
		if(!$ct) {
			throw new NotFoundHttpException('Unknown content type');
		}
			
		
		/** @var RevisionRepository $repositoryRev */
		$repositoryRev = $em->getRepository('Ems/CoreBundle:Revision');
		/** @var Revision $revision */
		$revision = $repositoryRev->findByOuuidAndContentTypeAndEnvironnement($ct, $ouuid, $env);
		if(!$revision) {
			throw new NotFoundHttpException('Unknown revision');
		}
		
		$success = $this->getNotificationService()->addNotification($templateId, $revision, $env);

		return $this->render( 'ajax/notification.json.twig', [
				'success' => $success,
		] );
	}
	
	
	/**
	 * @Route("/notification/cancel/{notification}", name="notification.cancel"))
     * @Method({"POST"})
	 */
	public function cancelNotificationsAction(Notification $notification, Request $request)
	{
		$this->getNotificationService()->setStatus($notification, 'cancelled');
		return $this->redirectToRoute('notifications.sent');
	}
	
	
	/**
	 * @Route("/notification/acknowledge/{notification}", name="notification.acknowledge"))
     * @Method({"POST"})
	 */
	public function acknowledgeNotificationsAction(Notification $notification, Request $request)
	{
		$this->getNotificationService()->setStatus($notification, 'acknowledged');
		return $this->redirectToRoute('notifications.inbox');
	}
	
	
	/**
	 * @Route("/notification/treat", name="notification.treat"))
     * @Method({"POST"})
	 */
	public function treatNotificationsAction(Request $request)
	{
		$treatNotification = new TreatNotifications();
		$form = $this->createForm(TreatNotificationsType::class, $treatNotification, [
		]);
		$form->handleRequest ( $request );
		/**@var TreatNotifications $treatNotification*/
		$treatNotification = $form->getNormData();
		$treatNotification->setAccept($form->get('accept')->isClicked());
		$treatNotification->setReject($form->get('reject')->isClicked());


		$em = $this->getDoctrine()->getManager();
		$repositoryNotification = $em->getRepository('Ems/CoreBundle:Notification');
		
		$publishIn = $this->get('ems.service.environment')->getAliasByName($treatNotification->getPublishTo());
// 		$unpublishFrom  = $this->get('ems.service.environment')->getAliasByName($treatNotification->getUnpublishfrom());
		
// 		if(!empty($publishIn) && !empty($unpublishFrom) && $publishIn == $unpublishFrom) {
// 			$this->addFlash('error', 'You can\'t publish in and unpublish from the same environment '.$unpublishFrom.' !');
// 		}
// 		else {
			foreach( $treatNotification->getNotifications() as $notificationId => $true ){
				/**@var Notification $notification*/
				$notification = $repositoryNotification->find($notificationId);
				if(empty($notification)) {
					$this->addFlash('error', 'Notification #'.$notification.' not found');
					continue;
				}
				
				if(!empty($publishIn)) {
					$this->getPublishService()->publish($notification->getRevision(), $publishIn);
				}
				
// 				if(!empty($unpublishFrom)) {
// 					$this->getPublishService()->unpublish($notification->getRevision(), $unpublishFrom);
// 				}
				
				if($treatNotification->getAccept()){
					$this->get('ems.service.notification')->accept($notification, $treatNotification);
				}
				
				if($treatNotification->getReject()){
					$this->get('ems.service.notification')->reject($notification, $treatNotification);
				}
			}
// 		}
		
		
		return $this->redirectToRoute('notifications.inbox');
	}
	
	
	/**
	 * @Route("/notification/menu", name="notification.menu"))
	 */
	public function menuNotificationAction()
	{
		// TODO use a servce to pass authorization_checker to repositoryNotification.
		$em = $this->getDoctrine()->getManager();
		$repositoryNotification = $em->getRepository('Ems/CoreBundle:Notification');
		$repositoryNotification->setAuthorizationChecker($this->get('security.authorization_checker'));
		
		$vars['counter'] = $this->get('ems.service.notification')->menuNotification();
		
		return $this->render('notification/menu.html.twig', $vars);
	}
	
	/**
	 * @Route("/notifications/list", name="notifications.list", defaults={"folder": "inbox"})
	 * @Route("/notifications/inbox", name="notifications.inbox", defaults={"folder": "inbox"})
	 * @Route("/notifications/sent", name="notifications.sent", defaults={"folder": "sent"})
	 */
	public function listNotificationsAction($folder, Request $request)
	{
 		$filters = $request->query->get('notification_form');
 		
		$notificationFilter = new NotificationFilter();
		
 		$form = $this->createForm(NotificationFormType::class, $notificationFilter, [
 				'method' => 'GET'
 		]);
 		$form->handleRequest ( $request );
 		
 		if($form->isSubmitted()){
 			$notificationFilter = $form->getData();
 		}
 		

 		
		//TODO: use a servce to pass authorization_checker to repositoryNotification.
		$em = $this->getDoctrine()->getManager();
		$repositoryNotification = $em->getRepository('Ems/CoreBundle:Notification');
		$repositoryNotification->setAuthorizationChecker($this->get('security.authorization_checker'));

		$countRejected = $this->getNotificationService()->countRejected();
		$countPending = $this->getNotificationService()->countPending();
		$countSent =  $this->getNotificationService()->countSent();
 		$count = $countRejected + $countPending;
		
		// for pagination
		$paging_size = $this->getParameter('paging_size');
		$lastPage = ceil($count/$paging_size);
		if(null != $request->query->get('page')){
			$page = $request->query->get('page');
		}
		else{
			$page = 1;
		}
		
		$notifications = [];
		$rejectedNotifications = [];
		if($folder == 'sent') {
			$notifications = $this->getNotificationService()->listSentNotifications(($page-1)*$paging_size, $paging_size, $filters);
			$lastPage = ceil($countSent/$paging_size);
		}
		else {
			$notifications = $this->getNotificationService()->listInboxNotifications(($page-1)*$paging_size, $paging_size, $filters);
			$rejectedNotifications = $this->getNotificationService()->listRejectedNotifications(($page-1)*$paging_size, $paging_size, $filters);
			$lastPage = ceil(($countRejected > $countPending?$countRejected:$countPending)/$paging_size);
				
		}

 		$treatNotification = new TreatNotifications();
//  		$forForm = [];
//  		foreach ($notifications as $notification){
//  			$forForm[$notification->getId()] = false;
//  		}
//  		$treatNotification->setNotifications($forForm);
 		
 		/**@var \Symfony\Component\Routing\RouterInterface $router*/
 		$router = $this->get('router');
 		$treatform = $this->createForm(TreatNotificationsType::class, $treatNotification, [
 				'action' => $router->generate('notification.treat'),
 				'notifications' => $notifications,
 		]);
		
		return $this->render('notification/list.html.twig', array(
				'counter' => $count,
				'notifications' => $notifications,
				'lastPage' => $lastPage,
				'paginationPath' => 'notifications.'.$folder,
				'page' => $page,
				'form' => $form->createView(),
				'treatform' => $treatform->createView(),
				'currentFilters' => $request->query,
				'folder' => $folder,
				'countPending' => $countPending,
				'countRejected' => $countRejected,
				'rejectedNotifications' => $rejectedNotifications,
				'countSent' => $countSent,
		));
	}
}