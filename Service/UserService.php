<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use EMS\CoreBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EMS\CoreBundle\Entity\AuthToken;

class UserService {
	/**@var Registry $doctrine */
	private $doctrine;
	/**@var Session $session*/
	private $session;
	/**@var TokenStorageInterface $tokenStorage */
	private $tokenStorage;
	
	private $currentUser;
	
	private $securityRoles;
	
	public function __construct(Registry $doctrine, Session $session, TokenStorageInterface $tokenStorage, $securityRoles) {
		$this->doctrine = $doctrine;
		$this->session = $session;
		$this->tokenStorage = $tokenStorage;
		$this->currentUser = null;
		$this->securityRoles = $securityRoles;
	}
	
	
	public function findUsernameByApikey($apiKey){
		$em = $this->doctrine->getManager();
		/**@var \Doctrine\ORM\EntityRepository */
		$repository = $em->getRepository('EMSCoreBundle:AuthToken');
		
		/**@var AuthToken $token*/
		$token = $repository->findOneBy([
				'value' => $apiKey
		]);
		if(empty($token)){
			return null;
		}
		return $token->getUser()->getUsername();
		
	}
	
	public function getUser($username, $detachIt = true) {
		$em = $this->doctrine->getManager();
		/**@var \Doctrine\ORM\EntityRepository */
		$repository = $em->getRepository('EMSCoreBundle:User');
		$user = $repository->findOneBy([
				'usernameCanonical' => $username
		]);
		
		if(!empty($user) && $detachIt) {
			$em->detach($user);			
		}
		
		return $user;
	}
	
	/**
	 * @return User
	 */
	public function getCurrentUser() {
		if(!$this->currentUser){
			$username = $this->tokenStorage->getToken()->getUsername();
			$this->currentUser = $this->getUser($username);
		}
		return $this->currentUser;
	}
	
	
	public function getUsersForRoleAndCircles($role, $circles) {
		/**@var EntityManagerInterface $em*/
		$em = $this->doctrine->getManager();
		$repository = $em->getRepository('EMSCoreBundle:User');
		return $repository->findForRoleAndCircles($role, $circles);
	}
	
	
	public function getAllUsers() {
		$em = $this->doctrine->getManager();
		/**@var \Doctrine\ORM\EntityRepository */
		$repository = $em->getRepository('EMSCoreBundle:User');
		return $repository->findBy([
				'enabled' => true
		]);
	}
	
	public  function getsecurityRoles() {
		return $this->securityRoles;
	}

}