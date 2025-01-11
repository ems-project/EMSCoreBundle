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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;

class UserService implements EntityServiceInterface
{
    private ?UserInterface $currentUser = null;

    final public const bool DONT_DETACH = false;

    /**
     * @param array<mixed> $securityRoles
     */
    public function __construct(
        private readonly Registry $doctrine,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly Security $security,
        private readonly UserRepository $userRepository,
        private readonly SearchRepository $searchRepository,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly array $securityRoles,
    ) {
    }

    public function isSuper(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_SUPER');
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
            throw new \RuntimeException(\sprintf('Unknown user object class: %s', $user::class));
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
        $username = $token->getUserIdentifier();
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
        $em->flush();
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
        $roles = [...['ROLE_USER'], ...\array_keys($roleHierarchy), ...['ROLE_API']];

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

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        return $this->userRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'user';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
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

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        $user = $this->getUser($name);
        if (!$user instanceof User && null !== $user) {
            throw new \RuntimeException(\sprintf('Unknown user object class: %s', $user::class));
        }

        return $user;
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }

    public function isCliSession(): bool
    {
        return 'cli' === \php_sapi_name();
    }

    public function inMyCircles(mixed $circles): bool
    {
        if (\is_array($circles) && 0 === \count($circles)) {
            return true;
        }

        if ($this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
            return true;
        }

        $user = $this->getCurrentUser(UserService::DONT_DETACH);

        if (\is_array($circles)) {
            return \count(\array_intersect($circles, $user->getCircles())) > 0;
        }

        return \in_array($circles, $user->getCircles());
    }
}
