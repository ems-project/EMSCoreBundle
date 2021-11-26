<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use EMS\CoreBundle\Core\UI\Menu;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Repository\SearchRepository;
use EMS\CoreBundle\Repository\UserRepository;
use EMS\CoreBundle\Repository\UserRepositoryInterface;
use EMS\CoreBundle\Security\CoreLdapUser;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;

class UserService implements EntityServiceInterface
{
    /** @var Registry */
    private $doctrine;
    /** @var Session */
    private $session;
    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var UserInterface|null */
    private $currentUser;

    private $securityRoles;

    private UserRepository $userRepository;
    private Security $security;

    public const DONT_DETACH = false;
    private SearchRepository $searchRepository;

    public function __construct(Registry $doctrine, Session $session, TokenStorageInterface $tokenStorage, Security $security, SearchRepository $searchRepository, $securityRoles)
    {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->currentUser = null;
        $this->securityRoles = $securityRoles;
        $this->security = $security;
        $this->searchRepository = $searchRepository;
        $this->userRepository = $doctrine->getManager()->getRepository(User::class);
    }

    public function searchUser(string $search): ?UserInterface
    {
        /** @var UserInterface[] $cache */
        static $cache = [];

        if (\array_key_exists($search, $cache)) {
            return $cache[$search];
        }

        $user = $this->userRepository->search($search);
        $cache[$search] = $user;

        return $user;
    }

    public function findUsernameByApikey($apiKey)
    {
        $em = $this->doctrine->getManager();
        /** @var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:AuthToken');

        /** @var AuthToken $token */
        $token = $repository->findOneBy([
                'value' => $apiKey,
        ]);
        if (empty($token)) {
            return null;
        }

        return $token->getUser()->getUsername();
    }

    public function getUserById($id)
    {
        $em = $this->doctrine->getManager();
        /** @var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:User');
        $user = $repository->findOneBy([
                'id' => $id,
        ]);

        return $user;
    }

    public function findUserByEmail($email)
    {
        $em = $this->doctrine->getManager();
        /** @var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:User');
        $user = $repository->findOneBy([
                'email' => $email,
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
        /** @var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:User');
        $user = $repository->findOneBy([
                'username' => $username,
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
        $roleHierarchy = $this->securityRoles;

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
        /** @var EntityManagerInterface $em */
        $em = $this->doctrine->getManager();

        /** @var UserRepositoryInterface $repository */
        $repository = $em->getRepository('EMSCoreBundle:User');

        return $repository->findForRoleAndCircles($role, $circles);
    }

    public function deleteUser(UserInterface $user)
    {
        /** @var EntityManagerInterface $em */
        $em = $this->doctrine->getManager();
        $em->remove($user);
    }

    public function getAllUsers()
    {
        $em = $this->doctrine->getManager();
        /** @var \Doctrine\ORM\EntityRepository */
        $repository = $em->getRepository('EMSCoreBundle:User');

        return $repository->findBy([
                'enabled' => true,
        ]);
    }

    public function getsecurityRoles()
    {
        return $this->securityRoles;
    }

    /**
     * @return array<string, string>
     */
    public function listUserDisplayProperties(): array
    {
        return [
            'Display name' => 'displayName',
            'Username' => 'username',
            'Email' => 'email',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function listUserRoles(): array
    {
        $roleHierarchy = $this->getsecurityRoles();
        $roles = \array_merge(['ROLE_USER'], \array_keys($roleHierarchy), ['ROLE_API']);

        return \array_combine($roles, $roles);
    }

    /**
     * @param string[] $roles
     *
     * @return User[]
     */
    public function findUsersWithRoles(array $roles): array
    {
        $users = $this->userRepository->findBy(['enabled' => true]);

        if (0 === \count($roles)) {
            return $users;
        }

        return \array_filter($users, function (User $user) use ($roles) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }

            return false;
        });
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        return $this->userRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'user';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        return $this->userRepository->countUsers($searchValue);
    }

    public function isGrantedRole(string $role): bool
    {
        return $this->security->isGranted($role);
    }

    public function getSidebarMenu(): Menu
    {
        $user = $this->getCurrentUser();
        $menu = new Menu('view.elements.side-menu.user.name', ['%name%' => $user->getDisplayName()]);

        $searches = $this->searchRepository->getByUsername($user->getUsername());
        if (!empty($searches)) {
            $link = $menu->addChild('view.elements.side-menu.user.searches', 'fa fa-search', 'elasticsearch.search');
            $link->setTranslation([]);
            foreach ($searches as $search) {
                $link->addChild($search->getName(), '', 'elasticsearch.search', ['searchId' => $search->getId()]);
            }
        }

        return $menu;
    }
}
