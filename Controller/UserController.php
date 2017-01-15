<?php
namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use EMS\CoreBundle\Entity\AuthToken;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class UserController extends AppController
{
	/**
	 * @Route("/user", name="user.index"))
	 */
	public function indexAction(Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var EntityRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:User');
		
		$users = $repository->findAll();
		return $this->render( 'EMSCoreBundle:user:index.html.twig', [
				'users' => $users
		] );
	}
	
	
	
	/**
	 * 
	 * @Route("/user/{id}/edit", name="user.edit")
	 */
	public function editUserAction($id, Request $request)
	{
		$user = $this->getUserService()->getUserById($id);
		// test if user exist before modified it
		if(!$user){
			throw $this->createNotFoundException('user not found');
		}
		
	
		$form = $this->createFormBuilder($user)
		->add('email', EmailType::class, array('label' => 'form.email'))
		->add('username', null, array(
				'label' => 'form.username', 
				'disabled' => true
		))
		->add('displayName', null, array(
				'label' => 'Display name',
		))
		->add('circles', ObjectPickerType::class, [
				'multiple' => TRUE,
				'type' => $this->container->getParameter('ems_core.circles_object'),
				'dynamicLoading' => true
				
		])
		->add('enabled', CheckboxType::class)
// 		->add('locked')
// 		->add('expiresAt', DateType::class, array(
// 				'required' => FALSE,
//    				'widget' => 'single_text',
// 				'format' => 'd/M/y',
//  				'html5' => FALSE,
// 				'attr' => array('class' => 'datepicker',
//  					'data-date-format' => 'dd/mm/yyyy',
// 					'data-today-highlight' => FALSE,
// 					'data-week-start' => 1,
// 					'data-days-of-week-highlighted' => true,
// 					'data-days-of-week-disabled' => false,
// 					'data-multidate' => FALSE,
					
// 				),
// 		))
		->add('allowedToConfigureWysiwyg', CheckboxType::class, [
				'required' => false,
		])
		->add('wysiwygProfile', ChoiceType::class, [
				'required' => true,
				'choices' => [
					'Standard' => 'standard',
					'Light' => 'light',
					'Full' => 'full',
					'Custom' => 'custom'
				]
		])
		->add('wysiwygOptions', TextareaType::class, [
				'required' => false,
				'label' => 'WYSIWYG custom options',
				'attr' => [
					'rows' => 8,
				]
		])
		->add('roles', ChoiceType::class, array('choices' => $this->getExistingRoles(),
	        'label' => 'Roles',
	        'expanded' => true,
	        'multiple' => true,
	        'mapped' => true,))
	    ->add ( 'update', SubmitEmsType::class, [ 
				'attr' => [ 
						'class' => 'btn-primary btn-sm ' 
				],
				'icon' => 'fa fa-save',
		] )
		->getForm();
		
		$form->handleRequest($request);
	
		if ($form->isSubmitted() && $form->isValid()) {
			$user = $form->getData();
// 			dump($user);exit;
// 			$continue = TRUE;
// 			$continue = $this->userExist($user, 'edit', $form);
			
// 			if ($continue) {
				$this->getUserService()->updateUser($user);
// 				$this->getDoctrine()->getManager()->flush();
				$this->addFlash(
						'notice',
						'User was modified!'
						);
				return $this->redirectToRoute('user.index');
			}
// 		}
	
		return $this->render('EMSCoreBundle:user:edit.html.twig', array(
				'form' => $form->createView(),
				'user' => $user
		));
	}
	
	/**
	 *
	 * @Route("/user/{id}/delete", name="user.delete")
	 */
	public function removeUserAction($id, Request $request)
	{
	
		$user = $this->getUserService()->getUserById($id);
		// test if user exist before modified it
		if(!$user){
			throw $this->createNotFoundException('user not found');
		}
		
		$this->getUserService()->deleteUser($user);
		$this->getDoctrine()->getManager()->flush();
		$this->addFlash(
				'notice',
				'User was deleted!'
				);
		return $this->redirectToRoute('user.index');
	}
	
	/**
	 *
	 * @Route("/user/{id}/enabling", name="user.enabling")
	 */
	public function enablingUserAction($id, Request $request)
	{
	
		$user = $this->getUserService()->getUserById($id);
		// test if user exist before modified it
		if(!$user){
			throw $this->createNotFoundException('user not found');
		}
		
		$message = "User was ";
		if ($user->isEnabled()) {
			$user->setEnabled(FALSE);
			$message = $message . "disabled !";
		} else {
			$user->setEnabled(TRUE);
			$message = $message . "enabled !";
		}
		
		$this->getUserService()->updateUser($user);
		$this->getDoctrine()->getManager()->flush();
		$this->addFlash(
				'notice',
				$message
				);
		return $this->redirectToRoute('user.index');
	}
	
	/**
	 *
	 * @Route("/user/{id}/locking", name="user.locking")
	 */
	public function lockingUserAction($id, Request $request)
	{
	
		$user = $this->getUserService()->getUserById($id);
		// test if user exist before modified it
		if(!$user){
			throw $this->createNotFoundException('user not found');
		}
		$message = "User was ";
		if ($user-> isLocked()) {
			$user->setLocked(FALSE);
			$message = $message . "unlocked !";
		} else {
			$user->setLocked(TRUE);
			$message = $message . "locked !";
		}
		
		$this->getUserService()->updateUser($user);
		$this->getDoctrine()->getManager()->flush();
		$this->addFlash(
				'notice',
				$message
				);
		return $this->redirectToRoute('user.index');
	}
	
	/**
	 *
	 * @Route("/user/{username}/apikey", name="EMS_user_apikey")
     * @Method({"POST"})
	 */
	public function apiKeyAction($username, Request $request)
	{
		$user = $this->getUserService()->getUser($username, false);
		
		$authToken = new AuthToken($user);

		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		$em->persist($authToken);
		$em->flush();
		
		$this->addFlash('notice', 'Here is a new API key for user '.$user->getUsername().' '.$authToken->getValue());
		
		return $this->redirectToRoute('user.index');
	}
	
	/**
	 *
	 * @Route("/profile/sidebar-collapse/{collapsed}", name="user.sidebar-collapse")
     * @Method({"POST"})
	 */
	public function sidebarCollapseAction($collapsed, Request $request)
	{
		$user = $this->getUserService()->getUser($this->getUserService()->getCurrentUser()->getUsername(), false);
		$user->setSidebarCollapse($collapsed);
		
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		$em->persist($user);
		$em->flush();
		
		return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
				'success' => true,
		] );
	}
	
	/**
	 * Test if email or username exist return on add or edit Form
	 */
	private function userExist ($user, $action, $form) {
		$exists = array('email' => $this->getUserService()->findUserByEmail($user->getEmail()), 'username' => $this->getUserService()->getUser($user->getUsername()));
		$messages = array('email' => 'User email already exist!', 'username' => 'Username already exist!');
		foreach ($exists as $key => $value) {
			if ($value instanceof User) {
				if ($action == 'add' or ($action == 'edit' and $value->getId() != $user->getId()))
				{
					$this->addFlash(
						'error',
						$messages[$key]
					);	
					return FALSE;
				}
			}
		}
		return TRUE;
	}
	
	private  function getExistingRoles() {
	    return $this->getUserService()->getExistingRoles();
	}
}