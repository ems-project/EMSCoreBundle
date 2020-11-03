<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Security\CoreLdapUser;
use EMS\CoreBundle\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserService
{
    /**@var Registry $doctrine */
    private $doctrine;
    /**@var Session $session*/
    private $session;
    /**@var TokenStorageInterface $tokenStorage */
    private $tokenStorage;

    /** @var UserInterface|null */
    private $currentUser;
    
    private $securityRoles;

    const DONT_DETACH = false;
    
    public function __construct(Registry $doctrine, Session $session, TokenStorageInterface $tokenStorage, $securityRoles)
    {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->currentUser = null;
        $this->securityRoles = $securityRoles;
    }
    
    
    public function findUsernameByApikey($apiKey)
    {
        $em = $this->doctrine->getManager();
        /**@var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:AuthToken');
        
        /**@var AuthToken $token*/
        $token = $repository->findOneBy([
                'value' => $apiKey
        ]);
        if (empty($token)) {
            return null;
        }
        return $token->getUser()->getUsername();
    }
    
    public function getUserById($id)
    {
        $em = $this->doctrine->getManager();
        /**@var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:User');
        $user = $repository->findOneBy([
                'id' => $id
        ]);
        
        return $user;
    }
    
    public function findUserByEmail($email)
    {
        $em = $this->doctrine->getManager();
        /**@var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:User');
        $user = $repository->findOneBy([
                'email' => $email
        ]);
    
        return $user;
    }
    
    public function updateUser($user)
    {
        $em = $this->doctrine->getManager();
        $em->persist($user);
        $em->flush();
    
        return $user;
    }
    
    
    public function getUser($username, $detachIt = true)
    {
        $em = $this->doctrine->getManager();
        /**@var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:User');
        $user = $repository->findOneBy([
                'username' => $username
        ]);

        if (empty($user) || !$detachIt) {
            return $user;
        }

        $clone = clone $user;
        $em->detach($clone);
        return $clone;
    }
    
    public function getCurrentUser(bool $detach = true): UserInterface
    {
        if ($this->currentUser) {
            return $this->currentUser;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            throw new \RuntimeException('Token is null, could not get the currentUser from token.');
        }
        $username = $token->getUsername();
        $this->currentUser = $this->getUser($username, $detach);

        if (null === $this->currentUser && $token->getUser() instanceof CoreLdapUser) {
            $this->currentUser = $token->getUser();
        }

        return $this->currentUser;
    }
    

    public function getExistingRoles()
    {
        $roleHierarchy = $this->securityRoles; //securityRolesthis->container->getParameter('security.role_hierarchy.roles');
        
        $out = [];
        
        foreach ($roleHierarchy as $parent => $children) {
            foreach ($children as $child) {
                if (empty($out[$child])) {
                    $out[$child] = $child;
                }
            }
            if (empty($out[$parent])) {
                $out[$parent] = $parent;
            }
        }

        $out['ROLE_COPY_PASTE'] = 'ROLE_COPY_PASTE';
        $out['ROLE_ALLOW_ALIGN'] = 'ROLE_ALLOW_ALIGN';
        $out['ROLE_DEFAULT_SEARCH'] = 'ROLE_DEFAULT_SEARCH';
        $out['ROLE_SUPER'] = 'ROLE_SUPER';
        $out['ROLE_API'] = 'ROLE_API';
        $out['ROLE_USER_READ'] = 'ROLE_USER_READ';
        $out['ROLE_USER_MANAGEMENT'] = 'ROLE_USER_MANAGEMENT';
        return $out;
    }
    
    public function getUsersForRoleAndCircles($role, $circles)
    {
        /**@var EntityManagerInterface $em*/
        $em = $this->doctrine->getManager();
        
        /**@var UserRepositoryInterface $repository */
        $repository = $em->getRepository('EMSCoreBundle:User');
        
        return $repository->findForRoleAndCircles($role, $circles);
    }
    
    
    public function deleteUser(UserInterface $user)
    {
        /**@var EntityManagerInterface $em*/
        $em = $this->doctrine->getManager();
        $em->remove($user);
    }

    public function getAllUsers()
    {
        $em = $this->doctrine->getManager();
        /**@var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:User');
        return $repository->findBy([
                'enabled' => true
        ]);
    }
    
    public function getsecurityRoles()
    {
        return $this->securityRoles;
    }
}
