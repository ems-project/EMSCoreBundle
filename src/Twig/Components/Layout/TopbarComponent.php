<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Components\Layout;

use EMS\CoreBundle\Core\UI\LayoutService;
use EMS\CoreBundle\Core\UI\Menu;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Service\Channel\ChannelService;
use EMS\CoreBundle\Service\NotificationService;

class TopbarComponent
{
    public function __construct(
        private readonly LayoutService $layoutService,
        private readonly ChannelService $channelService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function getStatus(): string
    {
        return $this->layoutService->getStatus();
    }

    public function getDashboards(): Menu
    {
        return $this->layoutService->getTopbarDashboardMenu();
    }

    /**
     * @return Channel[]
     */
    public function getChannels(): array
    {
        return $this->channelService->getAll();
    }

    public function getCountNotifications(): int
    {
        return $this->notificationService->countNotifications();
    }
}
