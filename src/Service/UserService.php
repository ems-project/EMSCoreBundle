<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Core\UI\Menu;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Repository\SearchRepository;
use EMS\CoreBundle\Repository\UserRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;

class UserService implements EntityServiceInterface
{
    private Registry $doctrine;
    private TokenStorageInterface $tokenStorage;
    private ?UserInterface $currentUser;

    /** @var array<mixed> */
    private array $securityRoles;

    private UserRepository $userRepository;
    private Security $security;

    public const DONT_DETACH = false;
    private SearchRepository $searchRepository;

    /**
     * @param array<mixed> $securityRoles
     */
    public function __construct(
        Registry $doctrine,
        TokenStorageInterface $tokenStorage,
        Security $security,
        UserRepository $userRepository,
        SearchRepository $searchRepository,
        array $securityRoles)
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

    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findOneBy(['id' => $id]);
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy(['email' => $email]);
    }

    public function updateUser(UserInterface $user): UserInterface
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

    public function getUser(string $username, bool $detachIt = true): ?UserInterface
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);

        if (null === $user) {
            return null;
        }
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException(\sprintf('Unknown user object class: %s', \get_class($user)));
        }

        if (!$detachIt) {
            return $user;
        }

        return clone $user;
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

    /**
     * @param string[] $circles
     *
     * @return User[]
     */
    public function getUsersForRoleAndCircles(string $role, array $circles): array
    {
        return $this->userRepository->findForRoleAndCircles($role, $circles);
    }

    public function deleteUser(UserInterface $user): void
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
        return $this->userRepository->findBy(['enabled' => true]);
    }

    /**
     * @return UserInterface[]
     */
    public function getAll(): array
    {
        return $this->userRepository->findAll();
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
        $roleHierarchy = $this->securityRoles;
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

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
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

    public function getByItemName(string $name): ?EntityInterface
    {
        $user = $this->getUser($name);
        if (!$user instanceof User && null !== $user) {
            throw new \RuntimeException(\sprintf('Unknown user object class: %s', \get_class($user)));
        }

        return $user;
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }

    public function isCliSession(): bool
    {
        return 'cli' === \php_sapi_name();
    }
}
