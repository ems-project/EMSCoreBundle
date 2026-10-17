<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\User\UserList;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Translations\Translations;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

use function Symfony\Component\Translation\t;

readonly class UserExtension
{
    public function __construct(
        private UserService $userService
    ) {
    }

    #[AsTwigFunction(name: 'emsco_is_super')]
    public function isSuper(): bool
    {
        return $this->userService->isSuper();
    }

    /**
     * @param string[] $roles
     */
    #[AsTwigFilter(name: 'emsco_all_granted')]
    public function allGranted(array $roles, bool $super = false): bool
    {
        if ($super && !$this->isSuper()) {
            return false;
        }

        return \array_all($roles, fn ($role) => $this->userService->isGrantedRole($role));
    }

    /**
     * @param string[] $roles
     */
    #[AsTwigFilter(name: 'emsco_one_granted')]
    public function oneGranted(array $roles, bool $super = false): bool
    {
        if ($super && !$this->isSuper()) {
            return false;
        }

        return \array_any($roles, fn ($role) => $this->userService->isGrantedRole($role));
    }

    #[AsTwigFilter(name: 'emsco_get_user')]
    public function getUser(string $username): ?UserInterface
    {
        return $this->userService->getUser($username);
    }

    #[AsTwigFunction(name: 'emsco_users_enabled')]
    public function getUsersEnabled(): UserList
    {
        return $this->userService->getEnabledUsers();
    }

    /**
     * @param string|string[] $circles
     */
    #[AsTwigFilter(name: 'emsco_in_my_circles')]
    public function inMyCircles(string|array $circles): bool
    {
        return $this->userService->inMyCircles($circles);
    }

    #[AsTwigFilter(name: 'emsco_display_name')]
    public function displayName(?string $username): string
    {
        return match ($username) {
            null, '' => 'N/A',
            default => $this->userService->searchUser($username)?->getDisplayName() ?? $username,
        };
    }

    /**
     * @param string[] $translations
     */
    #[AsTwigFunction(name: 'emsco_cant_be_finalized')]
    public function cantBeFinalized(string $message = '', array $translations = []): never
    {
        $user = $this->userService->getCurrentUser();
        if (!$user instanceof User) {
            throw new \RuntimeException(\sprintf('User class must be an instance of "%s".', User::class));
        }
        $message = Translations::fromSimpleArray($user->getLocales(), $translations, $message);
        if ('' === $message) {
            throw new CantBeFinalizedException(t('message.draft_cant_be_finalized', [], 'emsco-core'));
        }
        throw new CantBeFinalizedException(t('message.custom', ['message' => $message], 'emsco-core'));
    }
}
