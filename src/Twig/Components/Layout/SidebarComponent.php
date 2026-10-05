<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Components\Layout;

use EMS\CoreBundle\Core\UI\LayoutService;
use EMS\CoreBundle\Core\UI\Menu;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class SidebarComponent
{
    public function __construct(
        private readonly LayoutService $layoutService,
        private readonly Security $security,
    ) {
    }

    public function getStatus(): string
    {
        return $this->layoutService->getStatus();
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
