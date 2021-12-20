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
use EMS\CoreBundle\Security\CoreLdapUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;

class UserService implements EntityServiceInterface
{
    /** @var Registry */
    private $doctrine;
    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var UserInterface|null */
    private $currentUser;

    private $securityRoles;

    private UserRepository $userRepository;
    private Security $security;

    public const DONT_DETACH = false;
    private SearchRepository $searchRepository;

    public function __construct(Registry $doctrine, TokenStorageInterface $tokenStorage, Security $security, UserRepository $userRepository, SearchRepository $searchRepository, $securityRoles)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
        $this->currentUser = null;
        $this->securityRoles = $securityRoles;
        $this->security = $security;
        $this->searchRepository = $searchRepository;
        $this->userRepository = $userRepository;
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
        $user = $this->userRepository->findOneBy([
                'id' => $id,
        ]);

        return $user;
    }

    public function findUserByEmail($email)
    {
        $user = $this->userRepository->findOneBy([
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

    public function giveUser(string $username, bool $detachIt = true): UserInterface
    {
        $user = $this->getUser($username, $detachIt);
        if (null === $user) {
            throw new \RuntimeException('Unexpected null user object');
        }

        return $user;
    }

    public function getUser($username, $detachIt = true): ?UserInterface
    {
        $em = $this->doctrine->getManager();
        $user = $this->userRepository->findOneBy([
                'username' => $username,
        ]);
        if (null === $user) {
            return null;
        }
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException(\sprintf('Unknown user object class: %s', \get_class($user)));
        }

        if (!$detachIt) {
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
        if (null === $this->currentUser) {
            throw new \RuntimeException('Unexpected null user object');
        }

        return $this->currentUser;
    }

    /**
     * @return array<string, string>
     */
    public function getExistingRoles(): array
    {
        $roleHierarchy = $this->securityRoles;

        $out = [];

        foreach ($roleHierarchy as $parent => $children) {
            foreach ($children as $child) {
                if (empty($out[\strval($child)])) {
                    $out[\strval($child)] = \strval($child);
                }
            }
            if (empty($out[\strval($parent)])) {
                $out[\strval($parent)] = \strval($parent);
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
        return $this->userRepository->findForRoleAndCircles($role, $circles);
    }

    public function deleteUser(UserInterface $user)
    {
        /** @var EntityManagerInterface $em */
        $em = $this->doctrine->getManager();
        $em->remove($user);
    }

    /**
     * @return UserInterface[]
     */
    public function getAllUsers(): array
    {
        return $this->userRepository->findBy([
                'enabled' => true,
        ]);
    }

    /**
     * @return UserInterface[]
     */
    public function getAll(): array
    {
        return $this->userRepository->findAll();
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
