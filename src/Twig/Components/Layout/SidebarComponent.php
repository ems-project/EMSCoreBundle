<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Components\Layout;

use EMS\CoreBundle\Core\UI\LayoutService;
use EMS\CoreBundle\Core\UI\Menu;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

class SidebarComponent
{
    public function __construct(
        private readonly LayoutService $layoutService,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getStatus(): string
    {
        return $this->layoutService->getStatus();
    }

    public function isPathActive(?string $path): bool
    {
        if (null === $path || null === $request = $this->requestStack->getCurrentRequest()) {
            return false;
        }

        if ($request->getPathInfo() === $path) {
            return true;
        }

        return $this->layoutService->isPathActive($path);
    }

    /**
     * @return Menu[]
     */
    public function getMenus(): array
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $this->layoutService->getSidebarMenus($user) : [];
    }
}
