<?php
namespace EMS\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;


//TODO: deprecated controller delegated to FOSUser, but we still need it to implement the eMS users provider
//http://symfony.com/doc/current/cookbook/security/multiple_user_providers.html
//http://symfony.com/doc/current/cookbook/security/entity_provider.html
//http://symfony.com/doc/current/book/security.html#where-do-users-come-from-user-providers
//http://symfony.com/doc/current/cookbook/security/access_control.html
//http://symfony.com/doc/current/book/security.html

class SecurityController extends Controller
{
	/**
	 * @Route("/login_OLD", name="login")
	 */
	public function loginAction(Request $request)
	{
	    $authenticationUtils = $this->get('security.authentication_utils');
	
	    // get the login error if there is one
	    $error = $authenticationUtils->getLastAuthenticationError();
	
	    // last username entered by the user
	    $lastUsername = $authenticationUtils->getLastUsername();
	
	    return $this->render(
	        'EMSCoreBundle:security:login.html.twig',
	        array(
	            // last username entered by the user
	            'last_username' => $lastUsername,
	            'error'         => $error,
	        )
	    );
	}

	/**
	 * @Route("/login_check_OLD", name="login_check")
	 */
	public function loginCheckAction()
	{
		// this controller will not be executed,
		// as the route is handled by the Security system
	}
}
